<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the Spatie Permission team context for the current panel.
 *
 * This middleware configures Spatie's team_id context based on the current panel:
 * - Panels WITH tenancy: team_id = current tenant's ID
 * - Panels WITHOUT tenancy: team_id = null
 *
 * Guard filtering is handled natively by Spatie Permission.
 */
class SetPermissionsTeam
{
    public function __construct(
        protected PermissionRegistrar $permissionRegistrar,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $panel = Filament::getCurrentPanel();

        if ($panel?->hasTenancy()) {
            /** @var int|string|null $tenantKey */
            $tenantKey = Filament::getTenant()?->getKey();

            $this->permissionRegistrar->setPermissionsTeamId($tenantKey);
        } else {
            $this->permissionRegistrar->setPermissionsTeamId(null);
        }

        return $next($request);
    }
}
