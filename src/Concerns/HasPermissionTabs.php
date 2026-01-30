<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian\Concerns;

use Closure;
use Filament\Support\Enums\GridDirection;

/**
 * Trait for configuring permission tabs in role management.
 *
 * This trait composes several focused configuration traits for better organization:
 * - HasTabVisibility: Controls which tabs are shown
 * - HasTabIcons: Configures tab icons
 * - HasCheckboxConfiguration: Manages checkbox layout settings
 * - HasResourceSectionConfiguration: Resource section layout and appearance
 * - HasPermissionIcons: Other icon configuration (search, assigned, select-all)
 *
 * All methods are available on the main plugin class for backward compatibility.
 */
trait HasPermissionTabs
{
    use HasCheckboxConfiguration;
    use HasPermissionIcons;
    use HasResourceSectionConfiguration;
    use HasTabIcons;
    use HasTabVisibility;

    /**
     * Create a closure that inverts a boolean condition.
     *
     * @deprecated Use invertVisibilityCondition() from HasTabVisibility instead
     */
    protected function invertCondition(bool | Closure $condition): Closure
    {
        return $this->invertVisibilityCondition($condition);
    }

    /**
     * Parse a string grid direction into a GridDirection enum.
     *
     * @deprecated Use parseCheckboxGridDirection() from HasCheckboxConfiguration instead
     */
    protected function parseGridDirection(string $direction): GridDirection
    {
        return $this->parseCheckboxGridDirection($direction);
    }
}
