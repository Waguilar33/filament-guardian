<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian\Base\Roles\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Waguilar\FilamentGuardian\Facades\Guardian;
use Waguilar\FilamentGuardian\FilamentGuardianPlugin;
use Waguilar\FilamentGuardian\Schemas\PermissionsSchemaBuilder;

class BaseRoleForm
{
    public static function configure(Schema $schema): Schema
    {
        $plugin = FilamentGuardianPlugin::get();

        return $schema
            ->columns(1)
            ->components([
                static::buildDetailsSection($plugin),
                static::buildPermissionsSection($plugin),
            ]);
    }

    protected static function buildDetailsSection(FilamentGuardianPlugin $plugin): Section
    {
        $section = Section::make($plugin->getRoleSectionLabel())
            ->icon($plugin->getRoleSectionIcon())
            ->compact()
            ->schema([
                TextInput::make('name')
                    ->label(__('filament-guardian::filament-guardian.roles.attributes.name'))
                    ->required()
                    ->maxLength(255)
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: Guardian::uniqueRoleValidation(),
                    )
                    ->autofocus(),
            ]);

        $description = $plugin->getRoleSectionDescription();
        if ($description !== null) {
            $section->description($description);
        }

        if ($plugin->isRoleSectionAside()) {
            $section->aside();
        }

        return $section;
    }

    protected static function buildPermissionsSection(FilamentGuardianPlugin $plugin): Section
    {
        $section = Section::make($plugin->getPermissionsSectionLabel())
            ->icon($plugin->getPermissionsSectionIcon())
            ->compact()
            ->schema(
                PermissionsSchemaBuilder::make()
                    ->mode(PermissionsSchemaBuilder::MODE_ROLE)
                    ->build()
            );

        $description = $plugin->getPermissionsSectionDescription();
        if ($description !== null) {
            $section->description($description);
        }

        if ($plugin->isPermissionsSectionAside()) {
            $section->aside();
        }

        return $section;
    }
}
