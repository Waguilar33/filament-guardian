<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian\Concerns;

use Closure;
use Filament\Support\Concerns\EvaluatesClosures;

/**
 * Trait for configuring permission tab visibility.
 *
 * Controls which permission tabs (Resources, Pages, Widgets, Custom) are shown
 * in the role management interface.
 */
trait HasTabVisibility
{
    use EvaluatesClosures;

    protected bool | Closure | null $shouldShowResourcesTab = null;

    protected bool | Closure | null $shouldShowPagesTab = null;

    protected bool | Closure | null $shouldShowWidgetsTab = null;

    protected bool | Closure | null $shouldShowCustomPermissionsTab = null;

    public function showResourcesTab(bool | Closure $condition = true): static
    {
        $this->shouldShowResourcesTab = $condition;

        return $this;
    }

    public function hideResourcesTab(bool | Closure $condition = true): static
    {
        $this->shouldShowResourcesTab = $this->invertVisibilityCondition($condition);

        return $this;
    }

    public function showPagesTab(bool | Closure $condition = true): static
    {
        $this->shouldShowPagesTab = $condition;

        return $this;
    }

    public function hidePagesTab(bool | Closure $condition = true): static
    {
        $this->shouldShowPagesTab = $this->invertVisibilityCondition($condition);

        return $this;
    }

    public function showWidgetsTab(bool | Closure $condition = true): static
    {
        $this->shouldShowWidgetsTab = $condition;

        return $this;
    }

    public function hideWidgetsTab(bool | Closure $condition = true): static
    {
        $this->shouldShowWidgetsTab = $this->invertVisibilityCondition($condition);

        return $this;
    }

    public function showCustomPermissionsTab(bool | Closure $condition = true): static
    {
        $this->shouldShowCustomPermissionsTab = $condition;

        return $this;
    }

    public function hideCustomPermissionsTab(bool | Closure $condition = true): static
    {
        $this->shouldShowCustomPermissionsTab = $this->invertVisibilityCondition($condition);

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

    /**
     * Create a closure that inverts a boolean condition.
     */
    protected function invertVisibilityCondition(bool | Closure $condition): Closure
    {
        return static function () use ($condition): bool {
            $value = $condition instanceof Closure ? $condition() : $condition;

            return ! $value;
        };
    }
}
