<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian;

use Closure;
use Exception;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Unique;
use RuntimeException;
use Spatie\Permission\Contracts\Role as RoleContract;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Traits\HasRoles;

class FilamentGuardian
{
    public const DEFAULT_SUPER_ADMIN_ENABLED = false;

    public const DEFAULT_SUPER_ADMIN_ROLE_NAME = 'Super Admin';

    public const DEFAULT_SUPER_ADMIN_INTERCEPT = 'before';

    /** @var (Closure(class-string<Model>, array{name: string, email: string, password: string}): Model)|null */
    protected ?Closure $createUserCallback = null;

    protected function resolvePanel(?string $panelId): ?Panel
    {
        return $panelId !== null
            ? Filament::getPanel($panelId)
            : Filament::getCurrentPanel();
    }

    protected function resolvePluginForPanel(?string $panelId): ?FilamentGuardianPlugin
    {
        $panel = $this->resolvePanel($panelId);

        if ($panel === null && $panelId === null) {
            foreach (Filament::getPanels() as $candidate) {
                try {
                    $plugin = $candidate->getPlugin('filament-guardian');
                    if ($plugin instanceof FilamentGuardianPlugin) {
                        return $plugin;
                    }
                } catch (Exception) {
                    continue;
                }
            }

            return null;
        }

        if ($panel === null) {
            return null;
        }

        try {
            $plugin = $panel->getPlugin('filament-guardian');

            return $plugin instanceof FilamentGuardianPlugin ? $plugin : null;
        } catch (Exception) {
            return null;
        }
    }

    private function userUsesHasRolesTrait(mixed $user): bool
    {
        if (! is_object($user) && ! is_string($user)) {
            return false;
        }

        return in_array(HasRoles::class, class_uses_recursive($user), true);
    }

    /**
     * @param  int|string|null  $tenantId  Scopes to this tenant. Null uses Spatie team tracking when teams are enabled.
     * @return Builder<Role>
     */
    private function buildSuperAdminRoleQuery(string $guard, ?string $panelId, int | string | null $tenantId = null): Builder
    {
        $registrar = app(PermissionRegistrar::class);

        /** @var class-string<Role> $roleClass */
        $roleClass = $registrar->getRoleClass();

        $query = $roleClass::query()
            ->whereRaw('name = ?', [$this->getSuperAdminRoleName($panelId)])
            ->whereRaw('guard_name = ?', [$guard]);

        if ($tenantId !== null) {
            /** @var string $teamKey */
            $teamKey = config('permission.column_names.team_foreign_key', 'team_id');
            $query->where($teamKey, $tenantId);
        } elseif ($registrar->teams) {
            /** @var string $teamKey */
            $teamKey = $registrar->teamsKey;
            $query->where($teamKey, getPermissionsTeamId());
        }

        return $query;
    }

    /**
     * @param  int|string|null  $tenantId  Scopes to this tenant. Null uses Spatie team tracking when teams are enabled.
     * @return array<string, mixed>
     */
    private function buildSuperAdminRoleAttributes(string $guard, ?string $panelId, int | string | null $tenantId = null): array
    {
        $registrar = app(PermissionRegistrar::class);

        $attributes = [
            'name' => $this->getSuperAdminRoleName($panelId),
            'guard_name' => $guard,
        ];

        if ($tenantId !== null) {
            /** @var string $teamKey */
            $teamKey = config('permission.column_names.team_foreign_key', 'team_id');
            $attributes[$teamKey] = $tenantId;
        } elseif ($registrar->teams) {
            /** @var string $teamKey */
            $teamKey = $registrar->teamsKey;
            $attributes[$teamKey] = getPermissionsTeamId();
        }

        return $attributes;
    }

    /**
     * @return Closure(Unique): Unique
     *
     * @api
     */
    public static function uniqueRoleValidation(): Closure
    {
        return function (Unique $rule): Unique {
            $panel = Filament::getCurrentPanel() ?? throw new RuntimeException('No Filament panel is currently active.');
            $rule->where('guard_name', $panel->getAuthGuard());

            if (Filament::hasTenancy()) {
                /** @var string $teamKey */
                $teamKey = config('permission.column_names.team_foreign_key', 'team_id');
                $rule->where($teamKey, getPermissionsTeamId());
            }

            return $rule;
        };
    }

    public function isSuperAdminEnabled(?string $panelId = null): bool
    {
        /** @var bool */
        return $this->resolvePluginForPanel($panelId)?->isSuperAdminEnabled()
            ?? config('filament-guardian.super_admin.enabled', self::DEFAULT_SUPER_ADMIN_ENABLED);
    }

    public function getSuperAdminRoleName(?string $panelId = null): string
    {
        /** @var string */
        return $this->resolvePluginForPanel($panelId)?->getSuperAdminRoleName()
            ?? config('filament-guardian.super_admin.role_name', self::DEFAULT_SUPER_ADMIN_ROLE_NAME);
    }

    public function getSuperAdminIntercept(?string $panelId = null): string
    {
        /** @var string */
        return $this->resolvePluginForPanel($panelId)?->getSuperAdminIntercept()
            ?? config('filament-guardian.super_admin.intercept', self::DEFAULT_SUPER_ADMIN_INTERCEPT);
    }

    /** @api */
    public function isSuperAdminRole(RoleContract $role): bool
    {
        if (! $this->isSuperAdminEnabled()) {
            return false;
        }

        return $role->name === $this->getSuperAdminRoleName();
    }

    public function userIsSuperAdmin(mixed $user): bool
    {
        if (! $this->isSuperAdminEnabled()) {
            return false;
        }

        if (! $user instanceof Authenticatable) {
            return false;
        }

        if (! $this->userUsesHasRolesTrait($user)) {
            return false;
        }

        return (bool) ([$user, 'hasRole'])($this->getSuperAdminRoleName()); // @phpstan-ignore callable.nonCallable
    }

    /**
     * @throws RuntimeException If panel has tenancy enabled
     *
     * @api
     */
    public function createSuperAdminRole(?string $panelId = null): Role
    {
        $panel = $this->resolvePanel($panelId);

        if ($panel !== null && $panel->hasTenancy()) {
            throw new RuntimeException(
                "Panel '{$panel->getId()}' has tenancy enabled. Super-admin roles are auto-created when tenants are created. Use createSuperAdminRoleForTenant() instead."
            );
        }

        $guard = $panel?->getAuthGuard() ?? 'web';
        $registrar = app(PermissionRegistrar::class);

        /** @var class-string<Role> $roleClass */
        $roleClass = $registrar->getRoleClass();

        /** @var Role */
        return $roleClass::query()->firstOrCreate($this->buildSuperAdminRoleAttributes($guard, $panelId));
    }

    /** @api */
    public function createSuperAdminRoleForTenant(?Model $tenant = null, ?string $guard = null, ?string $panelId = null): Role
    {
        $panel = $this->resolvePanel($panelId);
        $tenant ??= Filament::getTenant();
        $guard ??= $panel?->getAuthGuard() ?? 'web';

        if ($tenant === null) {
            throw new RuntimeException('No tenant provided and no current Filament tenant is available.');
        }

        $registrar = app(PermissionRegistrar::class);

        /** @var class-string<Role> $roleClass */
        $roleClass = $registrar->getRoleClass();

        /** @var int|string $tenantKey */
        $tenantKey = $tenant->getKey();

        /** @var Role */
        return $roleClass::query()->firstOrCreate(
            $this->buildSuperAdminRoleAttributes($guard, $panelId, $tenantKey)
        );
    }

    /** @api */
    public function getSuperAdminRoleForTenant(?Model $tenant = null, ?string $guard = null, ?string $panelId = null): ?Role
    {
        if (! $this->isSuperAdminEnabled($panelId)) {
            return null;
        }

        $panel = $this->resolvePanel($panelId);
        $tenant ??= Filament::getTenant();
        $guard ??= $panel?->getAuthGuard() ?? 'web';

        if ($tenant === null) {
            throw new RuntimeException('No tenant provided and no current Filament tenant is available.');
        }

        /** @var int|string $tenantKey */
        $tenantKey = $tenant->getKey();

        /** @var Role|null */
        return $this->buildSuperAdminRoleQuery($guard, $panelId, $tenantKey)->first();
    }

    /**
     * @throws RuntimeException If panel has tenancy enabled
     *
     * @api
     */
    public function getSuperAdminRole(?string $panelId = null): ?Role
    {
        if (! $this->isSuperAdminEnabled($panelId)) {
            return null;
        }

        $panel = $this->resolvePanel($panelId);

        if ($panel !== null && $panel->hasTenancy()) {
            throw new RuntimeException(
                "Panel '{$panel->getId()}' has tenancy enabled. Use getSuperAdminRoleForTenant() instead."
            );
        }

        $guard = $panel?->getAuthGuard() ?? 'web';

        /** @var Role|null */
        return $this->buildSuperAdminRoleQuery($guard, $panelId)->first();
    }

    /** @api */
    public function assignSuperAdminTo(Authenticatable $user, ?string $panelId = null): void
    {
        $role = $this->getSuperAdminRole($panelId);

        if ($role === null) {
            throw new RuntimeException('Super-admin role does not exist. Ensure super-admin is enabled and the role has been created.');
        }

        if (! $this->userUsesHasRolesTrait($user)) {
            throw new RuntimeException('User model must use the HasRoles trait.');
        }

        ([$user, 'assignRole'])($role); // @phpstan-ignore callable.nonCallable
    }

    /** @api */
    public function assignSuperAdminToForTenant(Authenticatable $user, ?Model $tenant = null, ?string $guard = null, ?string $panelId = null): void
    {
        $role = $this->getSuperAdminRoleForTenant($tenant, $guard, $panelId);

        if ($role === null) {
            throw new RuntimeException('Super-admin role does not exist. Ensure super-admin is enabled and the role has been created.');
        }

        if (! $this->userUsesHasRolesTrait($user)) {
            throw new RuntimeException('User model must use the HasRoles trait.');
        }

        ([$user, 'assignRole'])($role); // @phpstan-ignore callable.nonCallable
    }

    /**
     * @param  Closure(class-string<Model>, array{name: string, email: string, password: string}): Model  $callback
     *
     * @api
     */
    public function createUserUsing(Closure $callback): void
    {
        $this->createUserCallback = $callback;
    }

    /**
     * @param  class-string<Model>  $userModel
     * @param  array{name: string, email: string, password: string}  $data
     *
     * @api
     */
    public function createUser(string $userModel, array $data): Model
    {
        if ($this->createUserCallback !== null) {
            return ($this->createUserCallback)($userModel, $data);
        }

        /** @var Model $user */
        $user = $userModel::query()->create([ // @phpstan-ignore argument.type
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        return $user;
    }
}
