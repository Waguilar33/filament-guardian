<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian\Concerns;

use BackedEnum;
use Closure;
use Filament\Support\Concerns\EvaluatesClosures;
use Filament\Support\Enums\GridDirection;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

trait HasPermissionTabs
{
    use EvaluatesClosures;

    protected bool | Closure | null $shouldShowResourcesTab = null;

    protected bool | Closure | null $shouldShowPagesTab = null;

    protected bool | Closure | null $shouldShowWidgetsTab = null;

    protected bool | Closure | null $shouldShowCustomPermissionsTab = null;

    protected bool | Closure | null $shouldCollapseResourceSections = null;

    /**
     * @var int|array<string, int>|Closure|null
     */
    protected int | array | Closure | null $permissionCheckboxColumns = null;

    protected GridDirection | Closure | null $permissionCheckboxGridDirection = null;

    /**
     * @var int|array<string, int>|Closure|null
     */
    protected int | array | Closure | null $resourceCheckboxColumns = null;

    protected GridDirection | Closure | null $resourceCheckboxGridDirection = null;

    /**
     * @var int|array<string, int>|Closure|null
     */
    protected int | array | Closure | null $pageCheckboxColumns = null;

    protected GridDirection | Closure | null $pageCheckboxGridDirection = null;

    /**
     * @var int|array<string, int>|Closure|null
     */
    protected int | array | Closure | null $widgetCheckboxColumns = null;

    protected GridDirection | Closure | null $widgetCheckboxGridDirection = null;

    /**
     * @var int|array<string, int>|Closure|null
     */
    protected int | array | Closure | null $customCheckboxColumns = null;

    protected GridDirection | Closure | null $customCheckboxGridDirection = null;

    /**
     * @var int|array<string, int>|Closure|null
     */
    protected int | array | Closure | null $resourceSectionColumns = null;

    protected bool | Closure | null $shouldShowResourceSectionIcon = null;

    protected string | BackedEnum | Htmlable | Closure | false | null $resourcesTabIcon = null;

    protected string | BackedEnum | Htmlable | Closure | false | null $pagesTabIcon = null;

    protected string | BackedEnum | Htmlable | Closure | false | null $widgetsTabIcon = null;

    protected string | BackedEnum | Htmlable | Closure | false | null $customTabIcon = null;

    protected string | BackedEnum | Htmlable | Closure | false | null $searchIcon = null;

    protected string | BackedEnum | Closure | false | null $permissionAssignedIcon = null;

    protected string | BackedEnum | Closure | false | null $selectAllOnIcon = null;

    protected string | BackedEnum | Closure | false | null $selectAllOffIcon = null;

    public function showResourcesTab(bool | Closure $condition = true): static
    {
        $this->shouldShowResourcesTab = $condition;

        return $this;
    }

    public function hideResourcesTab(bool | Closure $condition = true): static
    {
        $this->shouldShowResourcesTab = $this->invertCondition($condition);

        return $this;
    }

    public function showPagesTab(bool | Closure $condition = true): static
    {
        $this->shouldShowPagesTab = $condition;

        return $this;
    }

    public function hidePagesTab(bool | Closure $condition = true): static
    {
        $this->shouldShowPagesTab = $this->invertCondition($condition);

        return $this;
    }

    public function showWidgetsTab(bool | Closure $condition = true): static
    {
        $this->shouldShowWidgetsTab = $condition;

        return $this;
    }

    public function hideWidgetsTab(bool | Closure $condition = true): static
    {
        $this->shouldShowWidgetsTab = $this->invertCondition($condition);

        return $this;
    }

    public function showCustomPermissionsTab(bool | Closure $condition = true): static
    {
        $this->shouldShowCustomPermissionsTab = $condition;

        return $this;
    }

    public function hideCustomPermissionsTab(bool | Closure $condition = true): static
    {
        $this->shouldShowCustomPermissionsTab = $this->invertCondition($condition);

        return $this;
    }

    public function shouldShowResourcesTab(): bool
    {
        if ($this->shouldShowResourcesTab !== null) {
            /** @var bool $result */
            $result = $this->evaluate($this->shouldShowResourcesTab);

            return $result;
        }

        /** @var bool $result */
        $result = config('filament-guardian.role_resource.tabs.resources.visible', true);

        return $result;
    }

    public function shouldShowPagesTab(): bool
    {
        if ($this->shouldShowPagesTab !== null) {
            /** @var bool $result */
            $result = $this->evaluate($this->shouldShowPagesTab);

            return $result;
        }

        /** @var bool $result */
        $result = config('filament-guardian.role_resource.tabs.pages.visible', true);

        return $result;
    }

    public function shouldShowWidgetsTab(): bool
    {
        if ($this->shouldShowWidgetsTab !== null) {
            /** @var bool $result */
            $result = $this->evaluate($this->shouldShowWidgetsTab);

            return $result;
        }

        /** @var bool $result */
        $result = config('filament-guardian.role_resource.tabs.widgets.visible', true);

        return $result;
    }

    public function shouldShowCustomPermissionsTab(): bool
    {
        if ($this->shouldShowCustomPermissionsTab !== null) {
            /** @var bool $result */
            $result = $this->evaluate($this->shouldShowCustomPermissionsTab);

            return $result;
        }

        /** @var bool $result */
        $result = config('filament-guardian.role_resource.tabs.custom.visible', true);

        return $result;
    }

    public function collapseResourceSections(bool | Closure $condition = true): static
    {
        $this->shouldCollapseResourceSections = $condition;

        return $this;
    }

    public function shouldCollapseResourceSections(): bool
    {
        if ($this->shouldCollapseResourceSections !== null) {
            /** @var bool $result */
            $result = $this->evaluate($this->shouldCollapseResourceSections);

            return $result;
        }

        /** @var bool $result */
        $result = config('filament-guardian.role_resource.resource_sections.collapsed', false);

        return $result;
    }

    /**
     * Set the number of columns for permission checkboxes.
     *
     * @param  int|array<string, int>|Closure  $columns
     */
    public function permissionCheckboxColumns(int | array | Closure $columns): static
    {
        $this->permissionCheckboxColumns = $columns;

        return $this;
    }

    /**
     * Get the number of columns for permission checkboxes.
     *
     * @return int|array<string, int>
     */
    public function getPermissionCheckboxColumns(): int | array
    {
        if ($this->permissionCheckboxColumns !== null) {
            /** @var int|array<string, int> $result */
            $result = $this->evaluate($this->permissionCheckboxColumns);

            return $result;
        }

        /** @var int|array<string, int> $result */
        $result = config('filament-guardian.role_resource.tabs.default.checkbox_columns', 4);

        return $result;
    }

    /**
     * Set the grid direction for permission checkboxes.
     */
    public function permissionCheckboxGridDirection(GridDirection | Closure $direction): static
    {
        $this->permissionCheckboxGridDirection = $direction;

        return $this;
    }

    /**
     * Get the grid direction for permission checkboxes.
     */
    public function getPermissionCheckboxGridDirection(): GridDirection
    {
        if ($this->permissionCheckboxGridDirection !== null) {
            /** @var GridDirection $result */
            $result = $this->evaluate($this->permissionCheckboxGridDirection);

            return $result;
        }

        /** @var string $direction */
        $direction = config('filament-guardian.role_resource.tabs.default.checkbox_grid_direction', 'column');

        return $this->parseGridDirection($direction);
    }

    /**
     * Set the number of columns for resource checkboxes.
     *
     * @param  int|array<string, int>|Closure  $columns
     */
    public function resourceCheckboxColumns(int | array | Closure $columns): static
    {
        $this->resourceCheckboxColumns = $columns;

        return $this;
    }

    /**
     * Get the number of columns for resource checkboxes.
     *
     * @return int|array<string, int>
     */
    public function getResourceCheckboxColumns(): int | array
    {
        if ($this->resourceCheckboxColumns !== null) {
            /** @var int|array<string, int> $result */
            $result = $this->evaluate($this->resourceCheckboxColumns);

            return $result;
        }

        /** @var int|array<string, int>|null $configValue */
        $configValue = config('filament-guardian.role_resource.tabs.resources.checkbox_columns');

        return $configValue ?? $this->getPermissionCheckboxColumns();
    }

    /**
     * Set the grid direction for resource checkboxes.
     */
    public function resourceCheckboxGridDirection(GridDirection | Closure $direction): static
    {
        $this->resourceCheckboxGridDirection = $direction;

        return $this;
    }

    /**
     * Get the grid direction for resource checkboxes.
     */
    public function getResourceCheckboxGridDirection(): GridDirection
    {
        if ($this->resourceCheckboxGridDirection !== null) {
            /** @var GridDirection $result */
            $result = $this->evaluate($this->resourceCheckboxGridDirection);

            return $result;
        }

        /** @var string|null $configValue */
        $configValue = config('filament-guardian.role_resource.tabs.resources.checkbox_grid_direction');

        if ($configValue !== null) {
            return $this->parseGridDirection($configValue);
        }

        return $this->getPermissionCheckboxGridDirection();
    }

    /**
     * Set the number of columns for page checkboxes.
     *
     * @param  int|array<string, int>|Closure  $columns
     */
    public function pageCheckboxColumns(int | array | Closure $columns): static
    {
        $this->pageCheckboxColumns = $columns;

        return $this;
    }

    /**
     * Get the number of columns for page checkboxes.
     *
     * @return int|array<string, int>
     */
    public function getPageCheckboxColumns(): int | array
    {
        if ($this->pageCheckboxColumns !== null) {
            /** @var int|array<string, int> $result */
            $result = $this->evaluate($this->pageCheckboxColumns);

            return $result;
        }

        /** @var int|array<string, int>|null $configValue */
        $configValue = config('filament-guardian.role_resource.tabs.pages.checkbox_columns');

        return $configValue ?? $this->getPermissionCheckboxColumns();
    }

    /**
     * Set the grid direction for page checkboxes.
     */
    public function pageCheckboxGridDirection(GridDirection | Closure $direction): static
    {
        $this->pageCheckboxGridDirection = $direction;

        return $this;
    }

    /**
     * Get the grid direction for page checkboxes.
     */
    public function getPageCheckboxGridDirection(): GridDirection
    {
        if ($this->pageCheckboxGridDirection !== null) {
            /** @var GridDirection $result */
            $result = $this->evaluate($this->pageCheckboxGridDirection);

            return $result;
        }

        /** @var string|null $configValue */
        $configValue = config('filament-guardian.role_resource.tabs.pages.checkbox_grid_direction');

        if ($configValue !== null) {
            return $this->parseGridDirection($configValue);
        }

        return $this->getPermissionCheckboxGridDirection();
    }

    /**
     * Set the number of columns for widget checkboxes.
     *
     * @param  int|array<string, int>|Closure  $columns
     */
    public function widgetCheckboxColumns(int | array | Closure $columns): static
    {
        $this->widgetCheckboxColumns = $columns;

        return $this;
    }

    /**
     * Get the number of columns for widget checkboxes.
     *
     * @return int|array<string, int>
     */
    public function getWidgetCheckboxColumns(): int | array
    {
        if ($this->widgetCheckboxColumns !== null) {
            /** @var int|array<string, int> $result */
            $result = $this->evaluate($this->widgetCheckboxColumns);

            return $result;
        }

        /** @var int|array<string, int>|null $configValue */
        $configValue = config('filament-guardian.role_resource.tabs.widgets.checkbox_columns');

        return $configValue ?? $this->getPermissionCheckboxColumns();
    }

    /**
     * Set the grid direction for widget checkboxes.
     */
    public function widgetCheckboxGridDirection(GridDirection | Closure $direction): static
    {
        $this->widgetCheckboxGridDirection = $direction;

        return $this;
    }

    /**
     * Get the grid direction for widget checkboxes.
     */
    public function getWidgetCheckboxGridDirection(): GridDirection
    {
        if ($this->widgetCheckboxGridDirection !== null) {
            /** @var GridDirection $result */
            $result = $this->evaluate($this->widgetCheckboxGridDirection);

            return $result;
        }

        /** @var string|null $configValue */
        $configValue = config('filament-guardian.role_resource.tabs.widgets.checkbox_grid_direction');

        if ($configValue !== null) {
            return $this->parseGridDirection($configValue);
        }

        return $this->getPermissionCheckboxGridDirection();
    }

    /**
     * Set the number of columns for custom permission checkboxes.
     *
     * @param  int|array<string, int>|Closure  $columns
     */
    public function customCheckboxColumns(int | array | Closure $columns): static
    {
        $this->customCheckboxColumns = $columns;

        return $this;
    }

    /**
     * Get the number of columns for custom permission checkboxes.
     *
     * @return int|array<string, int>
     */
    public function getCustomCheckboxColumns(): int | array
    {
        if ($this->customCheckboxColumns !== null) {
            /** @var int|array<string, int> $result */
            $result = $this->evaluate($this->customCheckboxColumns);

            return $result;
        }

        /** @var int|array<string, int>|null $configValue */
        $configValue = config('filament-guardian.role_resource.tabs.custom.checkbox_columns');

        return $configValue ?? $this->getPermissionCheckboxColumns();
    }

    /**
     * Set the grid direction for custom permission checkboxes.
     */
    public function customCheckboxGridDirection(GridDirection | Closure $direction): static
    {
        $this->customCheckboxGridDirection = $direction;

        return $this;
    }

    /**
     * Get the grid direction for custom permission checkboxes.
     */
    public function getCustomCheckboxGridDirection(): GridDirection
    {
        if ($this->customCheckboxGridDirection !== null) {
            /** @var GridDirection $result */
            $result = $this->evaluate($this->customCheckboxGridDirection);

            return $result;
        }

        /** @var string|null $configValue */
        $configValue = config('filament-guardian.role_resource.tabs.custom.checkbox_grid_direction');

        if ($configValue !== null) {
            return $this->parseGridDirection($configValue);
        }

        return $this->getPermissionCheckboxGridDirection();
    }

    /**
     * Set the number of columns for resource sections grid.
     *
     * @param  int|array<string, int>|Closure  $columns
     */
    public function resourceSectionColumns(int | array | Closure $columns): static
    {
        $this->resourceSectionColumns = $columns;

        return $this;
    }

    /**
     * Get the number of columns for resource sections grid.
     *
     * @return int|array<string, int>
     */
    public function getResourceSectionColumns(): int | array
    {
        if ($this->resourceSectionColumns !== null) {
            /** @var int|array<string, int> $result */
            $result = $this->evaluate($this->resourceSectionColumns);

            return $result;
        }

        /** @var int|array<string, int> $result */
        $result = config('filament-guardian.role_resource.resource_sections.columns', 1);

        return $result;
    }

    /**
     * Show resource navigation icon in each resource section.
     */
    public function showResourceSectionIcon(bool | Closure $condition = true): static
    {
        $this->shouldShowResourceSectionIcon = $condition;

        return $this;
    }

    /**
     * Hide resource navigation icon in resource sections.
     */
    public function hideResourceSectionIcon(bool | Closure $condition = true): static
    {
        $this->shouldShowResourceSectionIcon = $this->invertCondition($condition);

        return $this;
    }

    /**
     * Check if resource section icon should be shown.
     */
    public function shouldShowResourceSectionIcon(): bool
    {
        if ($this->shouldShowResourceSectionIcon !== null) {
            /** @var bool $result */
            $result = $this->evaluate($this->shouldShowResourceSectionIcon);

            return $result;
        }

        /** @var bool $result */
        $result = config('filament-guardian.role_resource.resource_sections.icon', false);

        return $result;
    }

    /**
     * Set the icon for the Resources tab. Pass `false` for no icon.
     */
    public function resourcesTabIcon(string | BackedEnum | Htmlable | Closure | false | null $icon): static
    {
        $this->resourcesTabIcon = $icon;

        return $this;
    }

    /**
     * Get the icon for the Resources tab.
     */
    public function getResourcesTabIcon(): string | BackedEnum | Htmlable | null
    {
        if ($this->resourcesTabIcon !== null) {
            /** @var string|BackedEnum|Htmlable|false $result */
            $result = $this->evaluate($this->resourcesTabIcon);

            if ($result === false) {
                return null;
            }

            return $result;
        }

        /** @var string|false|null $configValue */
        $configValue = config('filament-guardian.role_resource.tabs.resources.icon');

        if ($configValue === false) {
            return null;
        }

        if ($configValue !== null) {
            return $configValue;
        }

        return Heroicon::OutlinedSquare3Stack3d;
    }

    /**
     * Set the icon for the Pages tab. Pass `false` for no icon.
     */
    public function pagesTabIcon(string | BackedEnum | Htmlable | Closure | false | null $icon): static
    {
        $this->pagesTabIcon = $icon;

        return $this;
    }

    /**
     * Get the icon for the Pages tab.
     */
    public function getPagesTabIcon(): string | BackedEnum | Htmlable | null
    {
        if ($this->pagesTabIcon !== null) {
            /** @var string|BackedEnum|Htmlable|false $result */
            $result = $this->evaluate($this->pagesTabIcon);

            if ($result === false) {
                return null;
            }

            return $result;
        }

        /** @var string|false|null $configValue */
        $configValue = config('filament-guardian.role_resource.tabs.pages.icon');

        if ($configValue === false) {
            return null;
        }

        if ($configValue !== null) {
            return $configValue;
        }

        return Heroicon::OutlinedDocumentText;
    }

    /**
     * Set the icon for the Widgets tab. Pass `false` for no icon.
     */
    public function widgetsTabIcon(string | BackedEnum | Htmlable | Closure | false | null $icon): static
    {
        $this->widgetsTabIcon = $icon;

        return $this;
    }

    /**
     * Get the icon for the Widgets tab.
     */
    public function getWidgetsTabIcon(): string | BackedEnum | Htmlable | null
    {
        if ($this->widgetsTabIcon !== null) {
            /** @var string|BackedEnum|Htmlable|false $result */
            $result = $this->evaluate($this->widgetsTabIcon);

            if ($result === false) {
                return null;
            }

            return $result;
        }

        /** @var string|false|null $configValue */
        $configValue = config('filament-guardian.role_resource.tabs.widgets.icon');

        if ($configValue === false) {
            return null;
        }

        if ($configValue !== null) {
            return $configValue;
        }

        return Heroicon::OutlinedChartBar;
    }

    /**
     * Set the icon for the Custom tab. Pass `false` for no icon.
     */
    public function customTabIcon(string | BackedEnum | Htmlable | Closure | false | null $icon): static
    {
        $this->customTabIcon = $icon;

        return $this;
    }

    /**
     * Get the icon for the Custom tab.
     */
    public function getCustomTabIcon(): string | BackedEnum | Htmlable | null
    {
        if ($this->customTabIcon !== null) {
            /** @var string|BackedEnum|Htmlable|false $result */
            $result = $this->evaluate($this->customTabIcon);

            if ($result === false) {
                return null;
            }

            return $result;
        }

        /** @var string|false|null $configValue */
        $configValue = config('filament-guardian.role_resource.tabs.custom.icon');

        if ($configValue === false) {
            return null;
        }

        if ($configValue !== null) {
            return $configValue;
        }

        return Heroicon::OutlinedCog6Tooth;
    }

    /**
     * Set the icon for the search input. Pass `false` for no icon.
     */
    public function searchIcon(string | BackedEnum | Htmlable | Closure | false | null $icon): static
    {
        $this->searchIcon = $icon;

        return $this;
    }

    /**
     * Get the icon for the search input.
     */
    public function getSearchIcon(): string | BackedEnum | Htmlable | null
    {
        if ($this->searchIcon !== null) {
            /** @var string|BackedEnum|Htmlable|false $result */
            $result = $this->evaluate($this->searchIcon);

            if ($result === false) {
                return null;
            }

            return $result;
        }

        /** @var string|false|null $configValue */
        $configValue = config('filament-guardian.role_resource.search_icon');

        if ($configValue === false) {
            return null;
        }

        if ($configValue !== null) {
            return $configValue;
        }

        return Heroicon::OutlinedMagnifyingGlass;
    }

    /**
     * Set the icon for assigned permissions in the view infolist. Pass `false` for no icon.
     */
    public function permissionAssignedIcon(string | BackedEnum | Closure | false | null $icon): static
    {
        $this->permissionAssignedIcon = $icon;

        return $this;
    }

    /**
     * Get the icon for assigned permissions in the view infolist.
     */
    public function getPermissionAssignedIcon(): string | BackedEnum | null
    {
        if ($this->permissionAssignedIcon !== null) {
            /** @var string|BackedEnum|false $result */
            $result = $this->evaluate($this->permissionAssignedIcon);

            if ($result === false) {
                return null;
            }

            return $result;
        }

        /** @var string|false|null $configValue */
        $configValue = config('filament-guardian.role_resource.permission_assigned_icon');

        if ($configValue === false) {
            return null;
        }

        if ($configValue !== null) {
            return $configValue;
        }

        return Heroicon::OutlinedCheckCircle;
    }

    /**
     * Set the icon for the select all toggle when ON. Pass `false` for no icon.
     */
    public function selectAllOnIcon(string | BackedEnum | Closure | false | null $icon): static
    {
        $this->selectAllOnIcon = $icon;

        return $this;
    }

    /**
     * Get the icon for the select all toggle when ON.
     */
    public function getSelectAllOnIcon(): string | BackedEnum | null
    {
        if ($this->selectAllOnIcon !== null) {
            /** @var string|BackedEnum|false $result */
            $result = $this->evaluate($this->selectAllOnIcon);

            if ($result === false) {
                return null;
            }

            return $result;
        }

        /** @var string|false|null $configValue */
        $configValue = config('filament-guardian.role_resource.select_all_toggle.on_icon');

        if ($configValue === false) {
            return null;
        }

        if ($configValue !== null) {
            return $configValue;
        }

        return Heroicon::OutlinedCheckCircle;
    }

    /**
     * Set the icon for the select all toggle when OFF. Pass `false` for no icon.
     */
    public function selectAllOffIcon(string | BackedEnum | Closure | false | null $icon): static
    {
        $this->selectAllOffIcon = $icon;

        return $this;
    }

    /**
     * Get the icon for the select all toggle when OFF.
     */
    public function getSelectAllOffIcon(): string | BackedEnum | null
    {
        if ($this->selectAllOffIcon !== null) {
            /** @var string|BackedEnum|false $result */
            $result = $this->evaluate($this->selectAllOffIcon);

            if ($result === false) {
                return null;
            }

            return $result;
        }

        /** @var string|false|null $configValue */
        $configValue = config('filament-guardian.role_resource.select_all_toggle.off_icon');

        if ($configValue === false) {
            return null;
        }

        if ($configValue !== null) {
            return $configValue;
        }

        return Heroicon::OutlinedXCircle;
    }

    /**
     * Parse a string grid direction into a GridDirection enum.
     */
    protected function parseGridDirection(string $direction): GridDirection
    {
        return match (mb_strtolower($direction)) {
            'row' => GridDirection::Row,
            default => GridDirection::Column,
        };
    }

    /**
     * Create a closure that inverts a boolean condition.
     */
    protected function invertCondition(bool | Closure $condition): Closure
    {
        return static function () use ($condition): bool {
            $value = $condition instanceof Closure ? $condition() : $condition;

            return ! $value;
        };
    }
}
