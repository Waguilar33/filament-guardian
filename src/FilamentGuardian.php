<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian;

use Closure;
use Exception;
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
     * Resolve the FilamentGuardianPlugin for a specific panel or the current panel.
     *
     * @param  string|null  $panelId  Panel ID to resolve, or null to use current panel
     */
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
     *
     * @param  string|null  $panelId  Panel ID to check, or null to use current panel context
     */
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

    /**
     * Get the configured super-admin role name.
     *
     * @param  string|null  $panelId  Panel ID to check, or null to use current panel context
     */
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

    /**
     * Get the configured intercept mode ('before' or 'after').
     *
     * @param  string|null  $panelId  Panel ID to check, or null to use current panel context
     */
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
     * Create the super-admin role for a panel context.
     *
     * Uses the panel's guard and tenant (if applicable).
     * The role has no permissions assigned - it bypasses checks via Gate.
     *
     * @param  string|null  $panelId  Panel ID to use, or null to use current panel context
     *
     * @throws RuntimeException If the panel has tenancy enabled (use createSuperAdminRoleForTenant instead)
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
     * @param  string|null  $panelId  Panel ID to get role name from, or null to use current panel context
     *
     * @api
     */
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
     * Get the super-admin role for a panel context.
     *
     * @param  string|null  $panelId  Panel ID to use, or null to use current panel context
     *
     * @throws RuntimeException If the panel has tenancy enabled
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
     * Assign the super-admin role to a user.
     *
     * @param  string|null  $panelId  Panel ID for non-tenant panels. If null, uses current Filament context.
     *
     * @api
     */
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
}
