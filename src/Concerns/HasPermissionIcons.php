<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian\Concerns;

use BackedEnum;
use Closure;
use Filament\Support\Concerns\EvaluatesClosures;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Trait for configuring permission-related icons.
 *
 * Controls icons for search input, assigned permissions indicator,
 * and select-all toggle states.
 */
trait HasPermissionIcons
{
    use EvaluatesClosures;

    protected string | BackedEnum | Htmlable | Closure | false | null $searchIcon = null;

    protected string | BackedEnum | Closure | false | null $permissionAssignedIcon = null;

    protected string | BackedEnum | Closure | false | null $selectAllOnIcon = null;

    protected string | BackedEnum | Closure | false | null $selectAllOffIcon = null;

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
}
