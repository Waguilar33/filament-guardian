<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian\Schemas;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Waguilar\FilamentGuardian\FilamentGuardianPlugin;
use Waguilar\FilamentGuardian\Support\RolePermissionData;

/**
 * Fluent builder for generating permissions form schemas.
 *
 * Supports two modes:
 * - MODE_ROLE: All permissions editable (for role forms)
 * - MODE_USER: Only shows permissions NOT inherited from roles (additive only)
 */
final class PermissionsSchemaBuilder
{
    public const MODE_ROLE = 'role';

    public const MODE_USER = 'user';

    private string $mode = self::MODE_ROLE;

    /** @var Collection<int, string> */
    private Collection $roleBasedPermissions;

    /**
     * Lookup array for O(1) permission containment checks.
     *
     * @var array<string, true>
     */
    private array $roleBasedPermissionsLookup = [];

    private bool $showSelectAllAction = true;

    private RolePermissionData $data;

    private bool $hasFilteredOptions = false;

    private function __construct()
    {
        /** @var Collection<int, string> $emptyCollection */
        $emptyCollection = collect();
        $this->roleBasedPermissions = $emptyCollection;
        $this->data = RolePermissionData::make();
    }

    public static function make(): self
    {
        return new self;
    }

    public function mode(string $mode): self
    {
        $this->mode = $mode;

        return $this;
    }

    /**
     * @param  Collection<int, string>  $permissions
     */
    public function roleBasedPermissions(Collection $permissions): self
    {
        /** @var Collection<int, string> $typed */
        $typed = $permissions->values();
        $this->roleBasedPermissions = $typed;

        // Build lookup array for O(1) containment checks
        $this->roleBasedPermissionsLookup = array_fill_keys($typed->all(), true);

        return $this;
    }

    /**
     * Show or hide the select all toggle.
     *
     * @api
     */
    public function showSelectAllAction(bool $show = true): self
    {
        $this->showSelectAllAction = $show;

        return $this;
    }

    /**
     * Build and return the schema components.
     *
     * @return array<int, Component>
     */
    public function build(): array
    {
        if ($this->mode === self::MODE_USER) {
            return $this->buildUserModeSchema();
        }

        return $this->buildRoleModeSchema();
    }

    /**
     * Get all permission field names used in the schema.
     *
     * @api
     *
     * @return array<int, string>
     */
    public function getAllFieldNames(): array
    {
        $fieldNames = [];

        foreach ($this->data->getResources() as $subject => $resource) {
            $fieldNames[] = 'resource_' . mb_strtolower($subject) . '_permissions';
        }

        if ($this->data->hasPages()) {
            $fieldNames[] = 'page_permissions';
        }

        if ($this->data->hasWidgets()) {
            $fieldNames[] = 'widget_permissions';
        }

        if ($this->data->hasCustom()) {
            $fieldNames[] = 'custom_permissions';
        }

        return $fieldNames;
    }

    /**
     * Get all available permissions.
     *
     * @api
     *
     * @return array<int, string>
     */
    public function getAllPermissions(): array
    {
        $permissions = [];

        foreach ($this->data->getResources() as $resource) {
            $permissions = [...$permissions, ...array_keys($resource['options'])];
        }

        if ($this->data->hasPages()) {
            $permissions = [...$permissions, ...$this->data->getPages()['permissions']->all()];
        }

        if ($this->data->hasWidgets()) {
            $permissions = [...$permissions, ...$this->data->getWidgets()['permissions']->all()];
        }

        if ($this->data->hasCustom()) {
            $permissions = [...$permissions, ...$this->data->getCustom()['permissions']->all()];
        }

        return $permissions;
    }

    /**
     * @return array<int, Component>
     */
    private function buildRoleModeSchema(): array
    {
        if (! $this->data->hasPermissions()) {
            return [];
        }

        /** @var array<int, Component> $schema */
        $schema = [];

        if ($this->showSelectAllAction) {
            $schema[] = $this->buildSelectAllToggle();
        }

        $schema[] = $this->buildPermissionsTabs();

        return $schema;
    }

    /**
     * @return array<int, Component>
     */
    private function buildUserModeSchema(): array
    {
        if (! $this->data->hasPermissions()) {
            return [];
        }

        // Build tabs first so we know if there are available permissions
        $tabs = $this->buildPermissionsTabs();

        /** @var array<int, Component> $schema */
        $schema = [];

        if ($this->roleBasedPermissions->isNotEmpty()) {
            $roleCount = $this->roleBasedPermissions->count();
            $schema[] = Section::make(__('filament-guardian::filament-guardian.users.permissions.role_permissions_title'))
                ->description(trans_choice('filament-guardian::filament-guardian.users.permissions.role_permissions_message', $roleCount, ['count' => $roleCount]))
                ->icon('heroicon-o-shield-check')
                ->iconColor('warning')
                ->compact()
                ->schema([]);
        }

        if (! $this->hasAvailablePermissions()) {
            $schema[] = Section::make()
                ->description(__('filament-guardian::filament-guardian.users.permissions.no_additional_permissions'))
                ->icon('heroicon-o-check-circle')
                ->iconColor('success')
                ->compact()
                ->schema([]);
        } else {
            if ($this->showSelectAllAction) {
                $schema[] = $this->buildSelectAllToggle();
            }
            $schema[] = $tabs;
        }

        return $schema;
    }

    /**
     * Check if there are any permissions available after filtering out role-based ones.
     */
    private function hasAvailablePermissions(): bool
    {
        return $this->hasFilteredOptions;
    }

    private function buildSelectAllToggle(): Toggle
    {
        $data = $this->data;
        $plugin = FilamentGuardianPlugin::get();
        $roleBasedLookup = $this->roleBasedPermissionsLookup;
        $isUserMode = $this->mode === self::MODE_USER;

        return Toggle::make('select_all')
            ->label(__('filament-guardian::filament-guardian.roles.actions.select_all'))
            ->onIcon($plugin->getSelectAllOnIcon())
            ->offIcon($plugin->getSelectAllOffIcon())
            ->inline()
            ->live()
            ->dehydrated(false)
            ->extraFieldWrapperAttributes(['class' => 'flex justify-end'])
            ->afterStateHydrated(function (Toggle $component, Get $get) use ($data, $roleBasedLookup, $isUserMode): void {
                $component->state($this->areAllPermissionsSelected($get, $data, $roleBasedLookup, $isUserMode));
            })
            ->afterStateUpdated(function (bool $state, Set $set) use ($data, $roleBasedLookup, $isUserMode): void {
                foreach ($data->getResources() as $subject => $resource) {
                    $fieldName = 'resource_' . mb_strtolower($subject) . '_permissions';
                    $allOptions = array_keys($resource['options']);

                    // In user mode, filter out role-based permissions from options (O(1) lookup)
                    if ($isUserMode) {
                        $allOptions = $this->filterOutRoleBased($allOptions, $roleBasedLookup);
                    }

                    $set($fieldName, $state ? $allOptions : []);
                }

                if ($data->hasPages()) {
                    $allOptions = $data->getPages()['permissions']->all();

                    if ($isUserMode) {
                        $allOptions = $this->filterOutRoleBased($allOptions, $roleBasedLookup);
                    }

                    $set('page_permissions', $state ? $allOptions : []);
                }

                if ($data->hasWidgets()) {
                    $allOptions = $data->getWidgets()['permissions']->all();

                    if ($isUserMode) {
                        $allOptions = $this->filterOutRoleBased($allOptions, $roleBasedLookup);
                    }

                    $set('widget_permissions', $state ? $allOptions : []);
                }

                if ($data->hasCustom()) {
                    $allOptions = $data->getCustom()['permissions']->all();

                    if ($isUserMode) {
                        $allOptions = $this->filterOutRoleBased($allOptions, $roleBasedLookup);
                    }

                    $set('custom_permissions', $state ? $allOptions : []);
                }
            });
    }

    /**
     * Filter out role-based permissions using O(1) lookup.
     *
     * @param  array<int, string>  $permissions
     * @param  array<string, true>  $roleBasedLookup
     * @return array<int, string>
     */
    private function filterOutRoleBased(array $permissions, array $roleBasedLookup): array
    {
        return array_values(array_filter(
            $permissions,
            fn (string $p): bool => ! isset($roleBasedLookup[$p])
        ));
    }

    /**
     * Check if all permissions are selected.
     *
     * @param  array<string, true>  $roleBasedLookup
     */
    private function areAllPermissionsSelected(
        Get $get,
        RolePermissionData $data,
        array $roleBasedLookup,
        bool $isUserMode
    ): bool {
        foreach ($data->getResources() as $subject => $resource) {
            $fieldName = 'resource_' . mb_strtolower($subject) . '_permissions';
            $optionCount = count($resource['options']);

            // In user mode, count only non-role-based options (O(1) lookup per item)
            if ($isUserMode) {
                $optionCount = count($this->filterOutRoleBased(array_keys($resource['options']), $roleBasedLookup));
            }

            // Skip if no options available
            if ($optionCount === 0) {
                continue;
            }

            /** @var array<int, string> $selected */
            $selected = $get($fieldName) ?? [];

            if (count($selected) !== $optionCount) {
                return false;
            }
        }

        if ($data->hasPages()) {
            $options = $data->getPages()['permissions']->all();
            $optionCount = $isUserMode
                ? count($this->filterOutRoleBased($options, $roleBasedLookup))
                : count($options);

            if ($optionCount > 0) {
                /** @var array<int, string> $selected */
                $selected = $get('page_permissions') ?? [];
                if (count($selected) !== $optionCount) {
                    return false;
                }
            }
        }

        if ($data->hasWidgets()) {
            $options = $data->getWidgets()['permissions']->all();
            $optionCount = $isUserMode
                ? count($this->filterOutRoleBased($options, $roleBasedLookup))
                : count($options);

            if ($optionCount > 0) {
                /** @var array<int, string> $selected */
                $selected = $get('widget_permissions') ?? [];
                if (count($selected) !== $optionCount) {
                    return false;
                }
            }
        }

        if ($data->hasCustom()) {
            $options = $data->getCustom()['permissions']->all();
            $optionCount = $isUserMode
                ? count($this->filterOutRoleBased($options, $roleBasedLookup))
                : count($options);

            if ($optionCount > 0) {
                /** @var array<int, string> $selected */
                $selected = $get('custom_permissions') ?? [];
                if (count($selected) !== $optionCount) {
                    return false;
                }
            }
        }

        return true;
    }

    private function buildPermissionsTabs(): Tabs
    {
        $tabs = [];

        if ($this->data->hasResources()) {
            $tabs[] = $this->buildResourcesTab();
        }

        if ($this->data->hasPages()) {
            $tabs[] = $this->buildPagesTab();
        }

        if ($this->data->hasWidgets()) {
            $tabs[] = $this->buildWidgetsTab();
        }

        if ($this->data->hasCustom()) {
            $tabs[] = $this->buildCustomTab();
        }

        return Tabs::make('permissions_tabs')
            ->tabs($tabs)
            ->columnSpanFull();
    }

    private function buildResourcesTab(): Tab
    {
        $plugin = FilamentGuardianPlugin::get();
        $showIcon = $plugin->shouldShowResourceSectionIcon();
        $sections = [];

        foreach ($this->data->getResources() as $subject => $resource) {
            $sections[] = $this->buildResourceSection(
                $subject,
                $resource['label'],
                $showIcon ? $resource['icon'] : null,
                $resource['options'],
            );
        }

        return Tab::make(__('filament-guardian::filament-guardian.roles.tabs.resources'))
            ->icon($plugin->getResourcesTabIcon())
            ->badge($this->data->getResourcePermissionCount())
            ->schema([
                TextInput::make('resources_search')
                    ->label(__('filament-guardian::filament-guardian.roles.search.label'))
                    ->placeholder(__('filament-guardian::filament-guardian.roles.search.placeholder'))
                    ->prefixIcon($plugin->getSearchIcon())
                    ->live(debounce: 300)
                    ->dehydrated(false),
                Grid::make($plugin->getResourceSectionColumns())
                    ->schema($sections),
            ]);
    }

    /**
     * @param  array<string, string>  $options
     */
    private function buildResourceSection(
        string $subject,
        string $label,
        ?string $icon,
        array $options,
    ): Section {
        $fieldName = 'resource_' . mb_strtolower($subject) . '_permissions';

        // In user mode, filter out role-based permissions from options
        $filteredOptions = $this->filterOptionsForUserMode($options);

        $checkboxList = CheckboxList::make($fieldName)
            ->hiddenLabel()
            ->options($filteredOptions)
            ->bulkToggleable();

        $section = Section::make($label)
            ->compact()
            ->collapsible()
            ->collapsed(FilamentGuardianPlugin::get()->shouldCollapseResourceSections())
            ->visible(function (Get $get) use ($label, $filteredOptions): bool {
                // Hide section if no options available after filtering
                if ($filteredOptions === []) {
                    return false;
                }

                $search = $get('resources_search');

                if (blank($search) || ! is_string($search)) {
                    return true;
                }

                return Str::contains(
                    str($label)->lower()->toString(),
                    str($search)->lower()->toString()
                );
            })
            ->schema([
                $this->configureCheckboxList($checkboxList, 'resource'),
            ]);

        if ($icon !== null) {
            $section->icon($icon);
        }

        return $section;
    }

    /**
     * Filter options to exclude role-based permissions in user mode.
     *
     * Uses O(1) lookup array for efficient filtering.
     *
     * @param  array<string, string>  $options
     * @return array<string, string>
     */
    private function filterOptionsForUserMode(array $options): array
    {
        if ($this->mode !== self::MODE_USER) {
            return $options;
        }

        $filtered = array_filter(
            $options,
            fn (string $key): bool => ! isset($this->roleBasedPermissionsLookup[$key]),
            ARRAY_FILTER_USE_KEY
        );

        if ($filtered !== []) {
            $this->hasFilteredOptions = true;
        }

        return $filtered;
    }

    private function buildPagesTab(): Tab
    {
        $pages = $this->data->getPages();
        $filteredOptions = $this->filterOptionsForUserMode($pages['options']);

        $checkboxList = CheckboxList::make('page_permissions')
            ->hiddenLabel()
            ->options($filteredOptions)
            ->searchable()
            ->bulkToggleable();

        return Tab::make(__('filament-guardian::filament-guardian.roles.tabs.pages'))
            ->icon(FilamentGuardianPlugin::get()->getPagesTabIcon())
            ->badge(count($filteredOptions))
            ->visible(fn (): bool => $filteredOptions !== [])
            ->schema([
                $this->configureCheckboxList($checkboxList, 'page'),
            ]);
    }

    private function buildWidgetsTab(): Tab
    {
        $widgets = $this->data->getWidgets();
        $filteredOptions = $this->filterOptionsForUserMode($widgets['options']);

        $checkboxList = CheckboxList::make('widget_permissions')
            ->hiddenLabel()
            ->options($filteredOptions)
            ->searchable()
            ->bulkToggleable();

        return Tab::make(__('filament-guardian::filament-guardian.roles.tabs.widgets'))
            ->icon(FilamentGuardianPlugin::get()->getWidgetsTabIcon())
            ->badge(count($filteredOptions))
            ->visible(fn (): bool => $filteredOptions !== [])
            ->schema([
                $this->configureCheckboxList($checkboxList, 'widget'),
            ]);
    }

    private function buildCustomTab(): Tab
    {
        $custom = $this->data->getCustom();
        $filteredOptions = $this->filterOptionsForUserMode($custom['options']);

        $checkboxList = CheckboxList::make('custom_permissions')
            ->hiddenLabel()
            ->options($filteredOptions)
            ->searchable()
            ->bulkToggleable();

        return Tab::make(__('filament-guardian::filament-guardian.roles.tabs.custom'))
            ->icon(FilamentGuardianPlugin::get()->getCustomTabIcon())
            ->badge(count($filteredOptions))
            ->visible(fn (): bool => $filteredOptions !== [])
            ->schema([
                $this->configureCheckboxList($checkboxList, 'custom'),
            ]);
    }

    private function configureCheckboxList(CheckboxList $checkboxList, string $type): CheckboxList
    {
        $plugin = FilamentGuardianPlugin::get();

        $columns = match ($type) {
            'resource' => $plugin->getResourceCheckboxColumns(),
            'page' => $plugin->getPageCheckboxColumns(),
            'widget' => $plugin->getWidgetCheckboxColumns(),
            'custom' => $plugin->getCustomCheckboxColumns(),
            default => $plugin->getPermissionCheckboxColumns(),
        };

        $direction = match ($type) {
            'resource' => $plugin->getResourceCheckboxGridDirection(),
            'page' => $plugin->getPageCheckboxGridDirection(),
            'widget' => $plugin->getWidgetCheckboxGridDirection(),
            'custom' => $plugin->getCustomCheckboxGridDirection(),
            default => $plugin->getPermissionCheckboxGridDirection(),
        };

        return $checkboxList
            ->columns($columns)
            ->gridDirection($direction);
    }
}
