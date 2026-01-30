<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian\Concerns;

use BackedEnum;
use Closure;
use Filament\Support\Concerns\EvaluatesClosures;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Trait for configuring permission tab icons.
 *
 * Controls the icons displayed on each permission tab in the role management interface.
 */
trait HasTabIcons
{
    use EvaluatesClosures;

    protected string | BackedEnum | Htmlable | Closure | false | null $resourcesTabIcon = null;

    protected string | BackedEnum | Htmlable | Closure | false | null $pagesTabIcon = null;

    protected string | BackedEnum | Htmlable | Closure | false | null $widgetsTabIcon = null;

    protected string | BackedEnum | Htmlable | Closure | false | null $customTabIcon = null;

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
}
