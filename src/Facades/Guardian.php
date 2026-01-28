<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian\Facades;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;
use Spatie\Permission\Contracts\Role as RoleContract;
use Spatie\Permission\Models\Role;

/**
 * @method static Closure uniqueRoleValidation()
 * @method static bool isSuperAdminEnabled()
 * @method static string getSuperAdminRoleName()
 * @method static string getSuperAdminIntercept()
 * @method static bool isSuperAdminRole(RoleContract $role)
 * @method static bool userIsSuperAdmin(mixed $user)
 * @method static Role createSuperAdminRole()
 * @method static Role createSuperAdminRoleForTenant(Model $tenant, string $guard)
 * @method static Role createSuperAdminRoleForPanel(string $panelId)
 * @method static Role|null getSuperAdminRole()
 * @method static Role|null getSuperAdminRoleForPanel(string $panelId)
 * @method static void assignSuperAdminTo(Authenticatable $user, ?string $panelId = null)
 *
 * @see \Waguilar\FilamentGuardian\FilamentGuardian
 */
class Guardian extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'filament-guardian';
    }
}
