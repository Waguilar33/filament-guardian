<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian;

use Closure;
use Exception;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
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
    /** @var (Closure(class-string<Model>, array{name: string, email: string, password: string}): Model)|null */
    protected ?Closure $createUserCallback = null;

    protected function resolvePluginForPanel(?string $panelId): ?FilamentGuardianPlugin
    {
        $panel = $panelId !== null
            ? Filament::getPanel($panelId)
            : Filament::getCurrentPanel();

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
        $plugin = $this->resolvePluginForPanel($panelId);

        if ($plugin !== null) {
            return $plugin->isSuperAdminEnabled();
        }

        /** @var bool $enabled */
        $enabled = config('filament-guardian.super_admin.enabled', false);

        return $enabled;
    }

    public function getSuperAdminRoleName(?string $panelId = null): string
    {
        $plugin = $this->resolvePluginForPanel($panelId);

        if ($plugin !== null) {
            return $plugin->getSuperAdminRoleName();
        }

        /** @var string $name */
        $name = config('filament-guardian.super_admin.role_name', 'Super Admin');

        return $name;
    }

    public function getSuperAdminIntercept(?string $panelId = null): string
    {
        $plugin = $this->resolvePluginForPanel($panelId);

        if ($plugin !== null) {
            return $plugin->getSuperAdminIntercept();
        }

        /** @var string $mode */
        $mode = config('filament-guardian.super_admin.intercept', 'before');

        return $mode;
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

        $usedTraits = class_uses_recursive($user);
        if (! in_array(HasRoles::class, $usedTraits, true)) {
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
        $panel = $panelId !== null
            ? Filament::getPanel($panelId)
            : Filament::getCurrentPanel();

        if ($panel !== null && $panel->hasTenancy()) {
            throw new RuntimeException(
                "Panel '{$panel->getId()}' has tenancy enabled. Super-admin roles are auto-created when tenants are created. Use createSuperAdminRoleForTenant() instead."
            );
        }

        $guard = $panel?->getAuthGuard() ?? 'web';
        $registrar = app(PermissionRegistrar::class);

        /** @var class-string<Role> $roleClass */
        $roleClass = $registrar->getRoleClass();

        $attributes = [
            'name' => $this->getSuperAdminRoleName($panelId),
            'guard_name' => $guard,
        ];

        if ($registrar->teams) {
            /** @var string $teamKey */
            $teamKey = $registrar->teamsKey;
            $attributes[$teamKey] = getPermissionsTeamId();
        }

        /** @var Role $role */
        $role = $roleClass::query()->firstOrCreate($attributes);

        return $role;
    }

    /** @api */
    public function createSuperAdminRoleForTenant(Model $tenant, string $guard, ?string $panelId = null): Role
    {
        $registrar = app(PermissionRegistrar::class);

        /** @var class-string<Role> $roleClass */
        $roleClass = $registrar->getRoleClass();

        /** @var string $teamKey */
        $teamKey = config('permission.column_names.team_foreign_key', 'team_id');

        /** @var int|string $tenantKey */
        $tenantKey = $tenant->getKey();

        $attributes = [
            'name' => $this->getSuperAdminRoleName($panelId),
            'guard_name' => $guard,
            $teamKey => $tenantKey,
        ];

        /** @var Role $role */
        $role = $roleClass::query()->firstOrCreate($attributes);

        return $role;
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

        $panel = $panelId !== null
            ? Filament::getPanel($panelId)
            : Filament::getCurrentPanel();

        if ($panel !== null && $panel->hasTenancy()) {
            throw new RuntimeException(
                "Panel '{$panel->getId()}' has tenancy enabled. Use getSuperAdminRole() within Filament context (with tenant set) instead."
            );
        }

        $guard = $panel?->getAuthGuard() ?? 'web';
        $registrar = app(PermissionRegistrar::class);

        /** @var class-string<Role> $roleClass */
        $roleClass = $registrar->getRoleClass();

        $query = $roleClass::query()
            ->where('name', $this->getSuperAdminRoleName($panelId))
            ->where('guard_name', $guard);

        if ($registrar->teams) {
            /** @var string $teamKey */
            $teamKey = $registrar->teamsKey;
            $query->where($teamKey, getPermissionsTeamId());
        }

        /** @var Role|null $role */
        $role = $query->first();

        return $role;
    }

    /** @api */
    public function assignSuperAdminTo(Authenticatable $user, ?string $panelId = null): void
    {
        $role = $this->getSuperAdminRole($panelId);

        if ($role === null) {
            throw new RuntimeException('Super-admin role does not exist. Ensure super-admin is enabled and the role has been created.');
        }

        $usedTraits = class_uses_recursive($user);
        if (! in_array(HasRoles::class, $usedTraits, true)) {
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
