<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian\Base\Roles;

use BackedEnum;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Resources\Resource;
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
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-shield-check';

    /**
     * Disable Filament's relationship-based tenant scoping.
     * Spatie's permission package handles scoping via team_id global scope,
     * which is set by the SetPermissionsTeam middleware.
     */
    protected static bool $isScopedToTenant = false;

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
     * @return Builder<Model>
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $panel = Filament::getCurrentPanel();
        $teamForeignKey = is_string($fk = config('permission.column_names.team_foreign_key')) ? $fk : 'team_id';

        $query->whereRaw('guard_name = ?', [$panel?->getAuthGuard()]);

        if ($panel?->hasTenancy()) {
            $query->whereRaw("{$teamForeignKey} = ?", [Filament::getTenant()?->getKey()]);
        } else {
            $query->whereNull($teamForeignKey);
        }

        return $query;
    }

    public static function getModelLabel(): string
    {
        return static::plugin()->getModelLabel() ?? parent::getModelLabel();
    }

    public static function getPluralModelLabel(): string
    {
        return static::plugin()->getPluralModelLabel() ?? parent::getPluralModelLabel();
    }

    public static function getSlug(?Panel $panel = null): string
    {
        return static::plugin()->getSlug() ?? parent::getSlug($panel);
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

    public static function getNavigationIcon(): string | BackedEnum | Htmlable | null
    {
        return static::plugin()->getNavigationIcon() ?? parent::getNavigationIcon();
    }

    public static function getActiveNavigationIcon(): string | BackedEnum | Htmlable | null
    {
        return static::plugin()->getActiveNavigationIcon() ?? parent::getActiveNavigationIcon();
    }

    public static function getNavigationLabel(): string
    {
        return static::plugin()->getNavigationLabel() ?? parent::getNavigationLabel();
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return static::plugin()->getNavigationGroup() ?? parent::getNavigationGroup();
    }

    public static function getNavigationSort(): ?int
    {
        return static::plugin()->getNavigationSort() ?? parent::getNavigationSort();
    }

    public static function getNavigationBadge(): ?string
    {
        return static::plugin()->getNavigationBadge() ?? parent::getNavigationBadge();
    }

    /**
     * @return string|array<string>|null
     */
    public static function getNavigationBadgeColor(): string | array | null
    {
        return static::plugin()->getNavigationBadgeColor() ?? parent::getNavigationBadgeColor();
    }

    public static function getNavigationParentItem(): ?string
    {
        return static::plugin()->getNavigationParentItem() ?? parent::getNavigationParentItem();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::plugin()->shouldRegisterNavigation();
    }

    protected static function plugin(): FilamentGuardianPlugin
    {
        return FilamentGuardianPlugin::get();
    }
}
