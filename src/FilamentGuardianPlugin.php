<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Spatie\Permission\PermissionRegistrar;
use Waguilar\FilamentGuardian\Concerns\HasNavigation;
use Waguilar\FilamentGuardian\Concerns\HasPermissionTabs;
use Waguilar\FilamentGuardian\Concerns\HasSectionConfiguration;
use Waguilar\FilamentGuardian\Http\Middleware\SetPermissionsTeam;
use Waguilar\FilamentGuardian\Resources\Roles\RoleResource;
use Waguilar\FilamentGuardian\Support\PermissionKeyBuilder;

class FilamentGuardianPlugin implements Plugin
{
    use HasNavigation;
    use HasPermissionTabs;
    use HasSectionConfiguration;

    protected ?Panel $panel = null;

    protected ?bool $superAdminEnabled = null;

    public function getId(): string
    {
        return 'filament-guardian';
    }

    public function register(Panel $panel): void
    {
        $this->panel = $panel;

        if (! $this->panelHasRoleResource($panel)) {
            $panel->resources([
                RoleResource::class,
            ]);
        }

        // Register middleware after authentication to apply guard/tenant scoping
        if ($panel->hasTenancy()) {
            $panel->tenantMiddleware([
                SetPermissionsTeam::class,
            ], isPersistent: true);
        } else {
            $panel->authMiddleware([
                SetPermissionsTeam::class,
            ], isPersistent: true);
        }
    }

    /**
     * Check if the panel already has a RoleResource registered.
     * This allows users to publish and customize the resource.
     */
    protected function panelHasRoleResource(Panel $panel): bool
    {
        return str(
            collect($panel->getResources())
                ->values()
                ->join(',')
        )->contains('\\RoleResource');
    }

    public function boot(Panel $panel): void
    {
        $this->panel = $panel;

        // Enable teams mode at runtime if panel has tenancy
        if ($panel->hasTenancy()) {
            app(PermissionRegistrar::class)->teams = true;
        }
    }

    public static function make(): static
    {
        /** @var static $instance */
        $instance = app(static::class);

        return $instance;
    }

    public static function get(): static
    {
        /** @var static $instance */
        $instance = app(static::class);

        /** @var static $plugin */
        $plugin = filament($instance->getId());

        return $plugin;
    }

    /**
     * Get the current panel.
     */
    public function getPanel(): ?Panel
    {
        return $this->panel;
    }

    /**
     * Check if the current panel has tenancy enabled.
     */
    public function hasTenancy(): bool
    {
        return $this->panel?->hasTenancy() ?? false;
    }

    /**
     * Get the tenant model class from the panel.
     */
    public function getTenantModel(): ?string
    {
        return $this->panel?->getTenantModel();
    }

    /**
     * Get the configured permission key builder.
     */
    public function getKeyBuilder(): PermissionKeyBuilder
    {
        /** @var string $separator */
        $separator = config('filament-guardian.permission_key.separator', ':');

        /** @var string $case */
        $case = config('filament-guardian.permission_key.case', 'pascal');

        return new PermissionKeyBuilder($separator, $case);
    }

    /**
     * Enable or disable super-admin for this panel.
     *
     * @api
     */
    public function superAdmin(bool $enabled = true): static
    {
        $this->superAdminEnabled = $enabled;

        return $this;
    }

    /**
     * Check if super-admin is enabled for this panel.
     *
     * Returns plugin-specific setting if set, otherwise falls back to config.
     */
    public function isSuperAdminEnabled(): bool
    {
        if ($this->superAdminEnabled !== null) {
            return $this->superAdminEnabled;
        }

        /** @var bool $enabled */
        $enabled = config('filament-guardian.super_admin.enabled', false);

        return $enabled;
    }
}
