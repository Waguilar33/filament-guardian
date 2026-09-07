<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian;

use Closure;
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
                $plugin = $this->guardianPluginOn($candidate);

                if ($plugin instanceof FilamentGuardianPlugin) {
                    return $plugin;
                }
            }

            return null;
        }

        if ($panel === null) {
            return null;
        }

        return $this->guardianPluginOn($panel);
    }

    private function guardianPluginOn(Panel $panel): ?FilamentGuardianPlugin
    {
        if (! $panel->hasPlugin('filament-guardian')) {
            return null;
        }

        $plugin = $panel->getPlugin('filament-guardian');

        return $plugin instanceof FilamentGuardianPlugin ? $plugin : null;
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

    /**
     * Whether the given role is a super-admin role.
     *
     * Deliberately matched on name alone, across every guard. Unlike
     * userIsSuperAdmin() this protects a row rather than granting power: its real
     * consumer is the model-level deleting/updating hooks, which fire for
     * programmatic writes outside any panel. Guard-scoping it there would leave
     * every other guard's super-admin role unprotected.
     *
     * The UI call sites never see a cross-guard role anyway -- BaseRoleResource
     * already scopes the query to the panel's guard. Note the role *name* is still
     * panel-resolved, so panels configured with different super-admin names protect
     * different rows.
     *
     * @api
     */
    public function isSuperAdminRole(RoleContract $role): bool
    {
        if (! $this->isSuperAdminEnabled()) {
            return false;
        }

        return $role->name === $this->getSuperAdminRoleName();
    }

    /**
     * Whether the user holds the super-admin role for the panel currently being served.
     *
     * This grants a blanket Gate bypass, so it is scoped to the current panel's
     * guard: a super admin of one panel is not a super admin of another.
     *
     * Outside a panel -- console commands, queued jobs, non-Filament routes --
     * there is no panel to take a guard from, and no honest way to guess one.
     * Falling back to the default panel's guard would silently pick one panel at
     * random in a multi-panel app, so the check stays guard-blind there instead:
     * it matches the super-admin role under any guard, which is how it behaved
     * everywhere before this became guard-aware.
     */
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

        $guard = Filament::getCurrentPanel()?->getAuthGuard();

        return (bool) ([$user, 'hasRole'])($this->getSuperAdminRoleName(), $guard); // @phpstan-ignore callable.nonCallable
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
