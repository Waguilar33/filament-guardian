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

    protected ?string $superAdminRoleName = null;

    protected ?string $superAdminIntercept = null;

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
        if ($this->superAdminEnabled !== null) {
            return $this->superAdminEnabled;
        }

        /** @var bool $enabled */
        $enabled = config('filament-guardian.super_admin.enabled', false);

        return $enabled;
    }

    /** @api */
    public function superAdminRoleName(string $name): static
    {
        $this->superAdminRoleName = $name;

        return $this;
    }

    public function getSuperAdminRoleName(): string
    {
        if ($this->superAdminRoleName !== null) {
            return $this->superAdminRoleName;
        }

        /** @var string $name */
        $name = config('filament-guardian.super_admin.role_name', 'Super Admin');

        return $name;
    }

    /** @api */
    public function superAdminIntercept(string $mode): static
    {
        $this->superAdminIntercept = $mode;

        return $this;
    }

    public function getSuperAdminIntercept(): string
    {
        if ($this->superAdminIntercept !== null) {
            return $this->superAdminIntercept;
        }

        /** @var string $mode */
        $mode = config('filament-guardian.super_admin.intercept', 'before');

        return $mode;
    }
}
