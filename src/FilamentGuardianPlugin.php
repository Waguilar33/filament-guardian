<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\PanelRegistry;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Waguilar\FilamentGuardian\Base\Roles\BaseRoleResource;
use Waguilar\FilamentGuardian\Concerns\HasContentTabs;
use Waguilar\FilamentGuardian\Concerns\HasNavigation;
use Waguilar\FilamentGuardian\Concerns\HasPermissionTabs;
use Waguilar\FilamentGuardian\Concerns\HasSectionConfiguration;
use Waguilar\FilamentGuardian\Http\Middleware\SetPermissionsTeam;
use Waguilar\FilamentGuardian\Resources\Roles\RoleResource;
use Waguilar\FilamentGuardian\Support\PermissionKeyBuilder;

class FilamentGuardianPlugin implements Plugin
{
    use HasContentTabs;
    use HasNavigation;
    use HasPermissionTabs;
    use HasSectionConfiguration;

    protected ?Panel $panel = null;

    protected ?bool $superAdminEnabled = null;

    protected ?string $superAdminRoleName = null;

    protected ?string $superAdminIntercept = null;

    public function getId(): string
    {
        return 'filament-guardian';
    }

    public function register(Panel $panel): void
    {
        $this->panel = $panel;

        $this->syncClusterToConfig();

        $this->registerRoleResourceWhenPanelIsComplete($panel);

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
     * Register the bundled role resource once the panel is fully assembled.
     *
     * Filament calls register() from the middle of the builder chain, so a panel
     * whose ->plugins() sits above its ->discoverResources() still reads as empty and
     * the host's own role resource is invisible here. PanelRegistry resolves once
     * every panel is built and before routes are registered, which makes
     * afterResolving() the first honest reading.
     *
     * Not boot(): it runs while a request is served, after routes and Livewire
     * components exist, so a resource added there gets neither.
     */
    protected function registerRoleResourceWhenPanelIsComplete(Panel $panel): void
    {
        $register = function () use ($panel): void {
            if ($this->panelHasRoleResource($panel)) {
                return;
            }

            // Every panel has now written the shared cluster key; re-sync so this
            // panel's value is the one registerToCluster() reads below.
            $this->syncClusterToConfig();

            $panel->resources([
                RoleResource::class,
            ]);

            // Registers the resource's pages as Livewire components. Already run once,
            // and safe to repeat: everything it registers is keyed by name.
            $panel->register();
        };

        // A panel built after boot has no registry resolution left to wait for.
        if (app()->isBooted()) {
            $register();

            return;
        }

        app()->afterResolving(PanelRegistry::class, $register);
    }

    /**
     * Temporarily sync the cluster value to config so Filament's
     * registerToCluster() can resolve it during $panel->resources().
     * This runs sequentially per panel, so multi-panel is safe.
     */
    protected function syncClusterToConfig(): void
    {
        $cluster = $this->cluster !== null
            ? $this->evaluate($this->cluster)
            : null;

        config([
            'filament-guardian.role_resource.navigation.cluster' => $cluster,
        ]);
    }

    /**
     * Whether the panel already registers a role resource of its own.
     *
     * Matches a subclass of the package's base resource, or any class actually named
     * RoleResource so a hand-written one still suppresses the bundled resource. A
     * substring match on the joined class names would also claim, say, a
     * `Resources\RoleResources\ThingResource`.
     */
    protected function panelHasRoleResource(Panel $panel): bool
    {
        foreach ($panel->getResources() as $resource) {
            if (is_subclass_of($resource, BaseRoleResource::class)) {
                return true;
            }

            if (class_basename($resource) === 'RoleResource') {
                return true;
            }
        }

        return false;
    }

    public function boot(Panel $panel): void
    {
        $this->panel = $panel;

        // Spatie's teams feature decides the pivot schema, so it has to be enabled in
        // config before the migration runs. Forcing the registrar flag on here instead
        // would leave every role query looking for a column that does not exist.
        if ($panel->hasTenancy() && ! app(PermissionRegistrar::class)->teams) {
            throw new RuntimeException(
                "Panel '{$panel->getId()}' uses tenancy, but Spatie's teams feature is disabled. "
                . "Set 'teams' => true in config/permission.php and run the permission migration. "
                . 'Enabling it after the migration has run requires a schema change.'
            );
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

    public function getPanel(): ?Panel
    {
        return $this->panel;
    }

    public function hasTenancy(): bool
    {
        return $this->panel?->hasTenancy() ?? false;
    }

    public function getTenantModel(): ?string
    {
        return $this->panel?->getTenantModel();
    }

    public function getKeyBuilder(): PermissionKeyBuilder
    {
        /** @var string $separator */
        $separator = config('filament-guardian.permission_key.separator', ':');

        /** @var string $case */
        $case = config('filament-guardian.permission_key.case', 'pascal');

        return new PermissionKeyBuilder($separator, $case);
    }

    /** @api */
    public function superAdmin(bool $enabled = true): static
    {
        $this->superAdminEnabled = $enabled;

        return $this;
    }

    public function isSuperAdminEnabled(): bool
    {
        /** @var bool */
        return $this->superAdminEnabled ?? config('filament-guardian.super_admin.enabled', FilamentGuardian::DEFAULT_SUPER_ADMIN_ENABLED);
    }

    /** @api */
    public function superAdminRoleName(string $name): static
    {
        $this->superAdminRoleName = $name;

        return $this;
    }

    public function getSuperAdminRoleName(): string
    {
        /** @var string */
        return $this->superAdminRoleName ?? config('filament-guardian.super_admin.role_name', FilamentGuardian::DEFAULT_SUPER_ADMIN_ROLE_NAME);
    }

    /** @api */
    public function superAdminIntercept(string $mode): static
    {
        $this->superAdminIntercept = $mode;

        return $this;
    }

    public function getSuperAdminIntercept(): string
    {
        /** @var string */
        return $this->superAdminIntercept ?? config('filament-guardian.super_admin.intercept', FilamentGuardian::DEFAULT_SUPER_ADMIN_INTERCEPT);
    }
}
