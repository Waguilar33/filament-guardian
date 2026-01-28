<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian;

use BackedEnum;
use Filament\Events\TenantSet;
use Filament\Facades\Filament;
use Filament\Support\Assets\Asset;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Facades\FilamentIcon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Livewire\Features\SupportTesting\Testable;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Spatie\Permission\Contracts\Role;
use Spatie\Permission\PermissionRegistrar;
use Waguilar\FilamentGuardian\Commands\FilamentGuardianCommand;
use Waguilar\FilamentGuardian\Commands\GeneratePoliciesCommand;
use Waguilar\FilamentGuardian\Commands\PublishRoleResourceCommand;
use Waguilar\FilamentGuardian\Commands\SetupSuperAdminCommand;
use Waguilar\FilamentGuardian\Commands\SyncPermissionsCommand;
use Waguilar\FilamentGuardian\Contracts\PermissionKeyBuilder as PermissionKeyBuilderContract;
use Waguilar\FilamentGuardian\Exceptions\SuperAdminProtectedException;
use Waguilar\FilamentGuardian\Facades\Guardian;
use Waguilar\FilamentGuardian\Support\PermissionKeyBuilder;
use Waguilar\FilamentGuardian\Testing\TestsFilamentGuardian;

class FilamentGuardianServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-guardian';

    public static string $viewNamespace = 'filament-guardian';

    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package->name(static::$name)
            ->hasCommands($this->getCommands())
            ->hasInstallCommand(function (InstallCommand $command) {
                $command
                    ->publishConfigFile()
                    ->publishMigrations()
                    ->askToRunMigrations()
                    ->askToStarRepoOnGitHub('Waguilar33/filament-guardian');
            });

        $configFileName = $package->shortName();

        if (file_exists($package->basePath("/../config/{$configFileName}.php"))) {
            $package->hasConfigFile();
        }

        if (file_exists($package->basePath('/../database/migrations'))) {
            $package->hasMigrations($this->getMigrations());
        }

        if (file_exists($package->basePath('/../resources/lang'))) {
            $package->hasTranslations();
        }

        if (file_exists($package->basePath('/../resources/views'))) {
            $package->hasViews(static::$viewNamespace);
        }
    }

    public function packageRegistered(): void
    {
        $this->app->scoped('filament-guardian', fn (): FilamentGuardian => new FilamentGuardian);

        $this->app->singleton(PermissionKeyBuilderContract::class, function () {
            /** @var class-string<PermissionKeyBuilderContract> $builderClass */
            $builderClass = config(
                'filament-guardian.permission_key.builder',
                PermissionKeyBuilder::class
            );

            /** @var string $separator */
            $separator = config('filament-guardian.permission_key.separator', ':');

            /** @var string $case */
            $case = config('filament-guardian.permission_key.case', 'pascal');

            return new $builderClass($separator, $case);
        });
    }

    public function packageBooted(): void
    {
        $packageName = $this->getAssetPackageName();

        // Asset Registration
        if ($packageName !== null) {
            FilamentAsset::register(
                $this->getAssets(),
                $packageName
            );

            FilamentAsset::registerScriptData(
                $this->getScriptData(),
                $packageName
            );
        }

        // Icon Registration
        $icons = $this->getIcons();
        if ($icons !== []) {
            FilamentIcon::register($icons);
        }

        // Handle Stubs
        if (app()->runningInConsole()) {
            foreach (app(Filesystem::class)->files(__DIR__ . '/../stubs/') as $file) {
                $this->publishes([
                    $file->getRealPath() => base_path("stubs/filament-guardian/{$file->getFilename()}"),
                ], 'filament-guardian-stubs');
            }
        }

        // Testing
        Testable::mixin(new TestsFilamentGuardian);

        // Register super-admin Gate callback
        $this->registerSuperAdminGate();

        // Register super-admin role protection
        $this->registerSuperAdminProtection();

        // Register tenant observer for auto-creating super-admin role
        $this->registerTenantObserver();

        // Listen for TenantSet event to set permissions team ID
        $this->registerTenantSetListener();

        // Set role defaults when creating via Filament
        $this->registerRoleDefaults();
    }

    protected function registerSuperAdminGate(): void
    {
        if (! Guardian::isSuperAdminEnabled()) {
            return;
        }

        $callback = static fn (mixed $user, string $ability): ?bool => Guardian::userIsSuperAdmin($user) ? true : null;

        if (Guardian::getSuperAdminIntercept() === 'after') {
            Gate::after($callback);
        } else {
            Gate::before($callback);
        }
    }

    protected function registerSuperAdminProtection(): void
    {
        $registrar = app(PermissionRegistrar::class);

        /** @var class-string $roleClass */
        $roleClass = $registrar->getRoleClass();

        // Prevent updating super-admin role (check original name since $role->name may be dirty)
        // Role name is resolved at runtime to support per-panel configuration
        $roleClass::updating(function (Role & Model $role): void {
            if (! Guardian::isSuperAdminEnabled()) {
                return;
            }

            $originalName = $role->getOriginal('name');
            if ($originalName === Guardian::getSuperAdminRoleName()) {
                throw new SuperAdminProtectedException(
                    __('filament-guardian::filament-guardian.super_admin.cannot_edit')
                );
            }
        });

        // Prevent deleting super-admin role
        $roleClass::deleting(function (Role $role): void {
            if (! Guardian::isSuperAdminEnabled()) {
                return;
            }

            if ($role->name === Guardian::getSuperAdminRoleName()) {
                throw new SuperAdminProtectedException(
                    __('filament-guardian::filament-guardian.super_admin.cannot_delete')
                );
            }
        });
    }

    protected function registerTenantObserver(): void
    {
        // Get all panels and register observer for each tenant model
        $panels = Filament::getPanels();

        foreach ($panels as $panel) {
            if (! $panel->hasTenancy()) {
                continue;
            }

            // Check if this panel has FilamentGuardian plugin with super-admin enabled
            $plugin = $panel->getPlugin('filament-guardian');
            if (! $plugin instanceof FilamentGuardianPlugin) {
                continue;
            }

            if (! $plugin->isSuperAdminEnabled()) {
                continue;
            }

            /** @var class-string $tenantModel */
            $tenantModel = $panel->getTenantModel();
            $guard = $panel->getAuthGuard();
            $panelId = $panel->getId();

            // Register created observer for tenant model
            $tenantModel::created(function (Model $tenant) use ($guard, $panelId): void {
                Guardian::createSuperAdminRoleForTenant($tenant, $guard, $panelId);
            });
        }
    }

    protected function registerRoleDefaults(): void
    {
        $registrar = app(PermissionRegistrar::class);

        /** @var class-string $roleClass */
        $roleClass = $registrar->getRoleClass();

        $roleClass::creating(function (Role $role) use ($registrar): void {
            $panel = Filament::getCurrentPanel();
            if (! $panel) {
                return;
            }

            // Only set defaults if not already explicitly set
            // This allows createSuperAdminRoleForTenant() to work from any panel context
            if ($role->guard_name === null) {
                $role->guard_name = $panel->getAuthGuard();
            }

            if ($registrar->teams) {
                /** @var string $teamKey */
                $teamKey = $registrar->teamsKey;

                if ($role->{$teamKey} === null) {
                    $role->{$teamKey} = getPermissionsTeamId();
                }
            }
        });
    }

    protected function registerTenantSetListener(): void
    {
        Event::listen(TenantSet::class, function (TenantSet $event) {
            $registrar = app(PermissionRegistrar::class);

            if ($registrar->teams) {
                /** @var int|string $tenantKey */
                $tenantKey = $event->getTenant()->getKey();
                $registrar->setPermissionsTeamId($tenantKey);
            }
        });
    }

    protected function getAssetPackageName(): ?string
    {
        return 'waguilar33/filament-guardian';
    }

    /**
     * @return array<Asset>
     */
    protected function getAssets(): array
    {
        return [
            // AlpineComponent::make('filament-guardian', __DIR__ . '/../resources/dist/components/filament-guardian.js'),
            // Css::make('filament-guardian-styles', __DIR__ . '/../resources/dist/filament-guardian.css'),
            // Js::make('filament-guardian-scripts', __DIR__ . '/../resources/dist/filament-guardian.js'),
        ];
    }

    /**
     * @return array<class-string>
     */
    protected function getCommands(): array
    {
        return [
            FilamentGuardianCommand::class,
            GeneratePoliciesCommand::class,
            PublishRoleResourceCommand::class,
            SetupSuperAdminCommand::class,
            SyncPermissionsCommand::class,
        ];
    }

    /**
     * @return array<string, BackedEnum|Htmlable|string>
     */
    protected function getIcons(): array
    {
        return [];
    }

    /**
     * @return array<string>
     */
    protected function getRoutes(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getScriptData(): array
    {
        return [];
    }

    /**
     * @return array<string>
     */
    protected function getMigrations(): array
    {
        return [
            'create_filament-guardian_table',
        ];
    }
}
