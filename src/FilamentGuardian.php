<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Unique;
use RuntimeException;
use Spatie\Permission\Contracts\Role as RoleContract;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Traits\HasRoles;

class FilamentGuardian
{
    /**
     * Get the unique validation closure for role names.
     * Scopes uniqueness by guard and team (using Spatie's context).
     *
     * @return Closure(Unique): Unique
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

    /**
     * Check if super-admin support is enabled.
     */
    public function isSuperAdminEnabled(): bool
    {
        /** @var bool $enabled */
        $enabled = config('filament-guardian.super_admin.enabled', false);

        return $enabled;
    }

    /**
     * Get the configured super-admin role name.
     */
    public function getSuperAdminRoleName(): string
    {
        /** @var string $name */
        $name = config('filament-guardian.super_admin.role_name', 'Super Admin');

        return $name;
    }

    /**
     * Get the configured intercept mode ('before' or 'after').
     */
    public function getSuperAdminIntercept(): string
    {
        /** @var string $mode */
        $mode = config('filament-guardian.super_admin.intercept', 'before');

        return $mode;
    }

    /**
     * Check if the given role is the super-admin role.
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
     * Check if the given user has the super-admin role.
     */
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
     * Create the super-admin role for the current Filament context.
     *
     * Uses the current panel's guard and tenant (if applicable).
     * The role has no permissions assigned - it bypasses checks via Gate.
     *
     * Note: This method requires an active Filament panel context.
     * For seeders/commands, use createSuperAdminRoleForPanel() instead.
     *
     * @api
     */
    public function createSuperAdminRole(): Role
    {
        $panel = Filament::getCurrentPanel();
        $guard = $panel?->getAuthGuard() ?? 'web';
        $registrar = app(PermissionRegistrar::class);

        /** @var class-string<Role> $roleClass */
        $roleClass = $registrar->getRoleClass();

        $attributes = [
            'name' => $this->getSuperAdminRoleName(),
            'guard_name' => $guard,
        ];

        // Add team key if teams mode is enabled
        if ($registrar->teams) {
            /** @var string $teamKey */
            $teamKey = $registrar->teamsKey;
            $attributes[$teamKey] = getPermissionsTeamId();
        }

        /** @var Role $role */
        $role = $roleClass::query()->firstOrCreate($attributes);

        return $role;
    }

    /**
     * Create the super-admin role for a specific tenant.
     *
     * Used by the tenant observer when a new tenant is created.
     * Always includes tenant scoping regardless of current context.
     *
     * @api
     */
    public function createSuperAdminRoleForTenant(Model $tenant, string $guard): Role
    {
        $registrar = app(PermissionRegistrar::class);

        /** @var class-string<Role> $roleClass */
        $roleClass = $registrar->getRoleClass();

        /** @var string $teamKey */
        $teamKey = config('permission.column_names.team_foreign_key', 'team_id');

        /** @var int|string $tenantKey */
        $tenantKey = $tenant->getKey();

        $attributes = [
            'name' => $this->getSuperAdminRoleName(),
            'guard_name' => $guard,
            $teamKey => $tenantKey,
        ];

        /** @var Role $role */
        $role = $roleClass::query()->firstOrCreate($attributes);

        return $role;
    }

    /**
     * Create the super-admin role for a specific panel (non-tenant).
     *
     * Use this in seeders and commands where no Filament context exists.
     * This method only works for panels WITHOUT tenancy.
     *
     * @throws RuntimeException If the panel has tenancy enabled
     *
     * @api
     */
    public function createSuperAdminRoleForPanel(string $panelId): Role
    {
        $panel = Filament::getPanel($panelId);

        if ($panel->hasTenancy()) {
            throw new RuntimeException(
                "Panel '{$panelId}' has tenancy enabled. Super-admin roles are auto-created when tenants are created. Use createSuperAdminRoleForTenant() instead."
            );
        }

        $guard = $panel->getAuthGuard();
        $registrar = app(PermissionRegistrar::class);

        /** @var class-string<Role> $roleClass */
        $roleClass = $registrar->getRoleClass();

        $attributes = [
            'name' => $this->getSuperAdminRoleName(),
            'guard_name' => $guard,
        ];

        // Non-tenant panels don't have team scoping, so no team key needed

        /** @var Role $role */
        $role = $roleClass::query()->firstOrCreate($attributes);

        return $role;
    }

    /**
     * Get the super-admin role for the current Filament context.
     *
     * Note: This method requires an active Filament panel context.
     * For seeders/commands, use getSuperAdminRoleForPanel() instead.
     *
     * @api
     */
    public function getSuperAdminRole(): ?Role
    {
        if (! $this->isSuperAdminEnabled()) {
            return null;
        }

        $panel = Filament::getCurrentPanel();
        $guard = $panel?->getAuthGuard() ?? 'web';
        $registrar = app(PermissionRegistrar::class);

        /** @var class-string<Role> $roleClass */
        $roleClass = $registrar->getRoleClass();

        $query = $roleClass::query()
            ->where('name', $this->getSuperAdminRoleName())
            ->where('guard_name', $guard);

        // Scope by team if teams mode is enabled
        if ($registrar->teams) {
            /** @var string $teamKey */
            $teamKey = $registrar->teamsKey;
            $query->where($teamKey, getPermissionsTeamId());
        }

        /** @var Role|null $role */
        $role = $query->first();

        return $role;
    }

    /**
     * Get the super-admin role for a specific panel (non-tenant).
     *
     * Use this in seeders and commands where no Filament context exists.
     *
     * @throws RuntimeException If the panel has tenancy enabled
     *
     * @api
     */
    public function getSuperAdminRoleForPanel(string $panelId): ?Role
    {
        if (! $this->isSuperAdminEnabled()) {
            return null;
        }

        $panel = Filament::getPanel($panelId);

        if ($panel->hasTenancy()) {
            throw new RuntimeException(
                "Panel '{$panelId}' has tenancy enabled. Use getSuperAdminRole() within Filament context instead."
            );
        }

        $guard = $panel->getAuthGuard();
        $registrar = app(PermissionRegistrar::class);

        /** @var class-string<Role> $roleClass */
        $roleClass = $registrar->getRoleClass();

        // Non-tenant panels don't have team scoping
        /** @var Role|null $role */
        $role = $roleClass::query()
            ->where('name', $this->getSuperAdminRoleName())
            ->where('guard_name', $guard)
            ->first();

        return $role;
    }

    /**
     * Assign the super-admin role to a user.
     *
     * @param  string|null  $panelId  Panel ID for non-tenant panels. If null, uses current Filament context.
     *
     * @api
     */
    public function assignSuperAdminTo(Authenticatable $user, ?string $panelId = null): void
    {
        $role = $panelId !== null
            ? $this->getSuperAdminRoleForPanel($panelId)
            : $this->getSuperAdminRole();

        if ($role === null) {
            throw new RuntimeException('Super-admin role does not exist. Ensure super-admin is enabled and the role has been created.');
        }

        $usedTraits = class_uses_recursive($user);
        if (! in_array(HasRoles::class, $usedTraits, true)) {
            throw new RuntimeException('User model must use the HasRoles trait.');
        }

        ([$user, 'assignRole'])($role); // @phpstan-ignore callable.nonCallable
    }
}
