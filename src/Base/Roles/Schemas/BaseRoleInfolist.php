<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian\Base\Roles\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Spatie\Permission\Contracts\Role;
use Waguilar\FilamentGuardian\Facades\Guardian;
use Waguilar\FilamentGuardian\FilamentGuardianPlugin;
use Waguilar\FilamentGuardian\Support\RolePermissionData;

class BaseRoleInfolist
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
            ->columns()
            ->schema([
                TextEntry::make('name')
                    ->label(__('filament-guardian::filament-guardian.roles.attributes.name')),
                TextEntry::make('created_at')
                    ->label(__('filament-guardian::filament-guardian.roles.attributes.created_at'))
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->label(__('filament-guardian::filament-guardian.roles.attributes.updated_at'))
                    ->dateTime(),
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
            ->schema(fn (?Model $record): array => static::permissionsSchema($record));

        $description = $plugin->getPermissionsSectionDescription();
        if ($description !== null) {
            $section->description($description);
        }

        if ($plugin->isPermissionsSectionAside()) {
            $section->aside();
        }

        return $section;
    }

    /**
     * @return array<int, mixed>
     */
    protected static function permissionsSchema(?Model $record): array
    {
        if (! $record instanceof Role) {
            return [];
        }

        // Show special entry for super-admin role
        if (Guardian::isSuperAdminRole($record)) {
            return [static::buildSuperAdminEntry()];
        }

        $data = RolePermissionData::make();
        $rolePermissions = $data->getRolePermissions($record);
        $counts = $data->countAssigned($rolePermissions);
        $tabs = [];

        if ($data->hasResources() && $counts['resources'] > 0) {
            $tabs[] = static::buildResourcesTab($data, $rolePermissions, $counts['resources']);
        }

        if ($data->hasPages() && $counts['pages'] > 0) {
            $tabs[] = static::buildPagesTab($data, $rolePermissions, $counts['pages']);
        }

        if ($data->hasWidgets() && $counts['widgets'] > 0) {
            $tabs[] = static::buildWidgetsTab($data, $rolePermissions, $counts['widgets']);
        }

        if ($data->hasCustom() && $counts['custom'] > 0) {
            $tabs[] = static::buildCustomTab($data, $rolePermissions, $counts['custom']);
        }

        if (empty($tabs)) {
            return [
                TextEntry::make('no_permissions')
                    ->label('')
                    ->state(__('filament-guardian::filament-guardian.roles.messages.no_permissions')),
            ];
        }

        return [
            Tabs::make('permissions_tabs')
                ->tabs($tabs)
                ->columnSpanFull(),
        ];
    }

    /**
     * Build the entry displayed for super-admin roles.
     *
     * Override this method to customize the super-admin display.
     */
    protected static function buildSuperAdminEntry(): Component
    {
        return TextEntry::make('super_admin_status')
            ->hiddenLabel()
            ->icon('heroicon-o-shield-check')
            ->iconColor('warning')
            ->state(__('filament-guardian::filament-guardian.super_admin.full_access'));
    }

    /**
     * @param  Collection<int, string>  $rolePermissions
     */
    protected static function buildResourcesTab(
        RolePermissionData $data,
        Collection $rolePermissions,
        int $count,
    ): Tab {
        $plugin = FilamentGuardianPlugin::get();
        $showIcon = $plugin->shouldShowResourceSectionIcon();
        $sections = [];

        foreach ($data->getAssignedResources($rolePermissions) as $resource) {
            $permissionCount = count($resource['permissions']);
            $permissionEntries = [];

            $assignedIcon = $plugin->getPermissionAssignedIcon();

            foreach ($resource['permissions'] as $index => $permission) {
                $entry = TextEntry::make('perm_' . md5($resource['label']) . '_' . $index)
                    ->hiddenLabel()
                    ->iconColor('success')
                    ->state($permission);

                if ($assignedIcon !== null) {
                    $entry->icon($assignedIcon);
                }

                $permissionEntries[] = $entry;
            }

            $section = Section::make($resource['label'])
                ->description(trans_choice('filament-guardian::filament-guardian.roles.messages.permissions_count', $permissionCount, ['count' => $permissionCount]))
                ->compact()
                ->collapsible()
                ->collapsed()
                ->columns($plugin->getResourceCheckboxColumns())
                ->schema($permissionEntries);

            if ($showIcon && $resource['icon'] !== null) {
                $section->icon($resource['icon']);
            }

            $sections[] = $section;
        }

        return Tab::make(__('filament-guardian::filament-guardian.roles.tabs.resources'))
            ->icon($plugin->getResourcesTabIcon())
            ->badge($count)
            ->schema($sections);
    }

    /**
     * @param  Collection<int, string>  $rolePermissions
     */
    protected static function buildPagesTab(
        RolePermissionData $data,
        Collection $rolePermissions,
        int $count,
    ): Tab {
        $plugin = FilamentGuardianPlugin::get();
        $labels = $data->getAssignedPageLabels($rolePermissions);
        $assignedIcon = $plugin->getPermissionAssignedIcon();
        $entries = [];

        foreach ($labels as $index => $label) {
            $entry = TextEntry::make('page_' . $index)
                ->hiddenLabel()
                ->iconColor('success')
                ->state($label);

            if ($assignedIcon !== null) {
                $entry->icon($assignedIcon);
            }

            $entries[] = $entry;
        }

        return Tab::make(__('filament-guardian::filament-guardian.roles.tabs.pages'))
            ->icon($plugin->getPagesTabIcon())
            ->badge($count)
            ->columns($plugin->getPageCheckboxColumns())
            ->schema($entries);
    }

    /**
     * @param  Collection<int, string>  $rolePermissions
     */
    protected static function buildWidgetsTab(
        RolePermissionData $data,
        Collection $rolePermissions,
        int $count,
    ): Tab {
        $plugin = FilamentGuardianPlugin::get();
        $labels = $data->getAssignedWidgetLabels($rolePermissions);
        $assignedIcon = $plugin->getPermissionAssignedIcon();
        $entries = [];

        foreach ($labels as $index => $label) {
            $entry = TextEntry::make('widget_' . $index)
                ->hiddenLabel()
                ->iconColor('success')
                ->state($label);

            if ($assignedIcon !== null) {
                $entry->icon($assignedIcon);
            }

            $entries[] = $entry;
        }

        return Tab::make(__('filament-guardian::filament-guardian.roles.tabs.widgets'))
            ->icon($plugin->getWidgetsTabIcon())
            ->badge($count)
            ->columns($plugin->getWidgetCheckboxColumns())
            ->schema($entries);
    }

    /**
     * @param  Collection<int, string>  $rolePermissions
     */
    protected static function buildCustomTab(
        RolePermissionData $data,
        Collection $rolePermissions,
        int $count,
    ): Tab {
        $plugin = FilamentGuardianPlugin::get();
        $labels = $data->getAssignedCustomLabels($rolePermissions);
        $assignedIcon = $plugin->getPermissionAssignedIcon();
        $entries = [];

        foreach ($labels as $index => $label) {
            $entry = TextEntry::make('custom_' . $index)
                ->hiddenLabel()
                ->iconColor('success')
                ->state($label);

            if ($assignedIcon !== null) {
                $entry->icon($assignedIcon);
            }

            $entries[] = $entry;
        }

        return Tab::make(__('filament-guardian::filament-guardian.roles.tabs.custom'))
            ->icon($plugin->getCustomTabIcon())
            ->badge($count)
            ->columns($plugin->getCustomCheckboxColumns())
            ->schema($entries);
    }
}
