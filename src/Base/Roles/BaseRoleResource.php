<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian\Base\Roles;

use BackedEnum;
use Closure;
use Filament\Clusters\Cluster;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Resources\Resource;
use Filament\Resources\ResourceConfiguration;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\PermissionRegistrar;
use UnitEnum;
use Waguilar\FilamentGuardian\Base\Roles\RelationManagers\BaseUsersRelationManager;
use Waguilar\FilamentGuardian\Base\Roles\Schemas\BaseRoleForm;
use Waguilar\FilamentGuardian\Base\Roles\Schemas\BaseRoleInfolist;
use Waguilar\FilamentGuardian\Base\Roles\Tables\BaseRolesTable;
use Waguilar\FilamentGuardian\FilamentGuardianPlugin;

abstract class BaseRoleResource extends Resource
{
    protected static string | BackedEnum | null $navigationIcon = null;

    protected static ?Panel $registrationPanel = null;

    /**
     * @return class-string<Model>
     */
    public static function getModel(): string
    {
        /** @var class-string<Model> $model */
        $model = app(PermissionRegistrar::class)->getRoleClass();

        return $model;
    }

    /**
     * Filter roles by the current panel's guard.
     * Tenant scoping is handled by Spatie's global scope via SetPermissionsTeam middleware.
     *
     * @return Builder<Model>
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $panel = Filament::getCurrentPanel();

        $query
            ->whereRaw('guard_name = ?', [$panel?->getAuthGuard()])
            ->with('permissions');

        return $query;
    }

    public static function getModelLabel(): string
    {
        return static::$modelLabel ?? static::plugin()?->getModelLabel() ?? parent::getModelLabel();
    }

    public static function getPluralModelLabel(): string
    {
        return static::$pluralModelLabel ?? static::plugin()?->getPluralModelLabel() ?? parent::getPluralModelLabel();
    }

    public static function getSlug(?Panel $panel = null): string
    {
        if (filled(static::$slug)) {
            return static::$slug;
        }

        return static::plugin()?->getSlug() ?? parent::getSlug($panel);
    }

    public static function form(Schema $schema): Schema
    {
        return BaseRoleForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return BaseRoleInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BaseRolesTable::configure($table);
    }

    /**
     * @return array<class-string>
     */
    public static function getRelations(): array
    {
        return [
            BaseUsersRelationManager::class,
        ];
    }

    public static function registerRoutes(Panel $panel, ?Closure $registerPageRoutes = null, ?ResourceConfiguration $configuration = null): void
    {
        static::$registrationPanel = $panel;

        try {
            parent::registerRoutes($panel, $registerPageRoutes, $configuration);
        } finally {
            static::$registrationPanel = null;
        }
    }

    /**
     * @return class-string<Cluster>|null
     */
    public static function getCluster(): ?string
    {
        /** @var class-string<Cluster>|null */
        return parent::getCluster()
            ?? static::plugin()?->getCluster()
            ?? config('filament-guardian.role_resource.navigation.cluster');
    }

    public static function getNavigationIcon(): string | BackedEnum | Htmlable | null
    {
        return parent::getNavigationIcon()
            ?? static::plugin()?->getNavigationIcon()
            ?? 'heroicon-o-shield-check';
    }

    public static function getActiveNavigationIcon(): string | BackedEnum | Htmlable | null
    {
        if (static::$activeNavigationIcon !== null) {
            return static::$activeNavigationIcon;
        }

        return static::plugin()?->getActiveNavigationIcon()
            ?? static::getNavigationIcon();
    }

    public static function getNavigationLabel(): string
    {
        return static::$navigationLabel ?? static::plugin()?->getNavigationLabel() ?? parent::getNavigationLabel();
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return parent::getNavigationGroup() ?? static::plugin()?->getNavigationGroup();
    }

    public static function getNavigationSort(): ?int
    {
        return parent::getNavigationSort() ?? static::plugin()?->getNavigationSort();
    }

    public static function getNavigationBadge(): ?string
    {
        return parent::getNavigationBadge() ?? static::plugin()?->getNavigationBadge();
    }

    /**
     * @return string|array<string>|null
     */
    public static function getNavigationBadgeColor(): string | array | null
    {
        return parent::getNavigationBadgeColor() ?? static::plugin()?->getNavigationBadgeColor();
    }

    public static function getNavigationParentItem(): ?string
    {
        return parent::getNavigationParentItem() ?? static::plugin()?->getNavigationParentItem();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::plugin()?->shouldRegisterNavigation() ?? parent::shouldRegisterNavigation();
    }

    protected static function plugin(): ?FilamentGuardianPlugin
    {
        $panel = filament()->getCurrentPanel() ?? static::$registrationPanel;

        if (! $panel?->hasPlugin('filament-guardian')) {
            return null;
        }

        /** @var FilamentGuardianPlugin $plugin */
        $plugin = $panel->getPlugin('filament-guardian');

        return $plugin;
    }
}
