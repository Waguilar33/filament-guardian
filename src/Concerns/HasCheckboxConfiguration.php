<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian\Concerns;

use Closure;
use Filament\Support\Concerns\EvaluatesClosures;
use Filament\Support\Enums\GridDirection;

/**
 * Trait for configuring permission checkbox layout.
 *
 * Controls the number of columns and grid direction for permission checkboxes
 * in each tab of the role management interface.
 */
trait HasCheckboxConfiguration
{
    use EvaluatesClosures;

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

        return $this->parseCheckboxGridDirection($direction);
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
            return $this->parseCheckboxGridDirection($configValue);
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
            return $this->parseCheckboxGridDirection($configValue);
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
            return $this->parseCheckboxGridDirection($configValue);
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
            return $this->parseCheckboxGridDirection($configValue);
        }

        return $this->getPermissionCheckboxGridDirection();
    }

    /**
     * Parse a string grid direction into a GridDirection enum.
     */
    protected function parseCheckboxGridDirection(string $direction): GridDirection
    {
        return match (mb_strtolower($direction)) {
            'row' => GridDirection::Row,
            default => GridDirection::Column,
        };
    }
}
