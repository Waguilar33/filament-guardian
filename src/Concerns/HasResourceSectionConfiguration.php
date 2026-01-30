<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian\Concerns;

use Closure;
use Filament\Support\Concerns\EvaluatesClosures;

/**
 * Trait for configuring resource sections in permission tabs.
 *
 * Controls the layout and appearance of resource permission sections,
 * including columns, collapsible state, and icons.
 */
trait HasResourceSectionConfiguration
{
    use EvaluatesClosures;

    protected bool | Closure | null $shouldCollapseResourceSections = null;

    /**
     * @var int|array<string, int>|Closure|null
     */
    protected int | array | Closure | null $resourceSectionColumns = null;

    protected bool | Closure | null $shouldShowResourceSectionIcon = null;

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
        $this->shouldShowResourceSectionIcon = static function () use ($condition): bool {
            $value = $condition instanceof Closure ? $condition() : $condition;

            return ! $value;
        };

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
}
