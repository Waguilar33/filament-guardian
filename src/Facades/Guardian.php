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
 * @method static bool isSuperAdminEnabled(?string $panelId = null)
 * @method static string getSuperAdminRoleName(?string $panelId = null)
 * @method static string getSuperAdminIntercept(?string $panelId = null)
 * @method static bool isSuperAdminRole(RoleContract $role)
 * @method static bool userIsSuperAdmin(mixed $user)
 * @method static Role createSuperAdminRole(?string $panelId = null)
 * @method static Role createSuperAdminRoleForTenant(Model $tenant, string $guard, ?string $panelId = null)
 * @method static Role|null getSuperAdminRole(?string $panelId = null)
 * @method static void assignSuperAdminTo(Authenticatable $user, ?string $panelId = null)
 * @method static void createUserUsing(Closure $callback)
 * @method static Model createUser(string $userModel, array{name: string, email: string, password: string} $data)
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
