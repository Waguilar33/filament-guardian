<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian\Concerns;

use BackedEnum;
use Closure;
use Filament\Support\Concerns\EvaluatesClosures;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

trait HasSectionConfiguration
{
    use EvaluatesClosures;

    protected string | Closure | null $roleSectionLabel = null;

    protected string | Closure | null $roleSectionDescription = null;

    protected string | BackedEnum | Htmlable | Closure | false | null $roleSectionIcon = null;

    protected bool | Closure | null $roleSectionAside = null;

    protected string | Closure | null $permissionsSectionLabel = null;

    protected string | Closure | null $permissionsSectionDescription = null;

    protected string | BackedEnum | Htmlable | Closure | false | null $permissionsSectionIcon = null;

    protected bool | Closure | null $permissionsSectionAside = null;

    /**
     * Set the role section label.
     */
    public function roleSectionLabel(string | Closure $label): static
    {
        $this->roleSectionLabel = $label;

        return $this;
    }

    /**
     * Set the role section description.
     */
    public function roleSectionDescription(string | Closure $description): static
    {
        $this->roleSectionDescription = $description;

        return $this;
    }

    /**
     * Set the role section icon. Pass `false` for no icon.
     */
    public function roleSectionIcon(string | BackedEnum | Htmlable | Closure | false $icon): static
    {
        $this->roleSectionIcon = $icon;

        return $this;
    }

    /**
     * Set the role section as aside.
     */
    public function roleSectionAside(bool | Closure $condition = true): static
    {
        $this->roleSectionAside = $condition;

        return $this;
    }

    /**
     * Set the permissions section label.
     */
    public function permissionsSectionLabel(string | Closure $label): static
    {
        $this->permissionsSectionLabel = $label;

        return $this;
    }

    /**
     * Set the permissions section description.
     */
    public function permissionsSectionDescription(string | Closure $description): static
    {
        $this->permissionsSectionDescription = $description;

        return $this;
    }

    /**
     * Set the permissions section icon. Pass `false` for no icon.
     */
    public function permissionsSectionIcon(string | BackedEnum | Htmlable | Closure | false $icon): static
    {
        $this->permissionsSectionIcon = $icon;

        return $this;
    }

    /**
     * Set the permissions section as aside.
     */
    public function permissionsSectionAside(bool | Closure $condition = true): static
    {
        $this->permissionsSectionAside = $condition;

        return $this;
    }

    /**
     * Get the role section label.
     */
    public function getRoleSectionLabel(): string
    {
        if ($this->roleSectionLabel !== null) {
            /** @var string $result */
            $result = $this->evaluate($this->roleSectionLabel);

            return $result;
        }

        /** @var string|null $configValue */
        $configValue = config('filament-guardian.role_resource.sections.role.label');

        if ($configValue !== null) {
            return $configValue;
        }

        return __('filament-guardian::filament-guardian.roles.sections.role');
    }

    /**
     * Get the role section description.
     */
    public function getRoleSectionDescription(): ?string
    {
        if ($this->roleSectionDescription !== null) {
            /** @var string $result */
            $result = $this->evaluate($this->roleSectionDescription);

            return $result;
        }

        /** @var string|null $configValue */
        $configValue = config('filament-guardian.role_resource.sections.role.description');

        return $configValue;
    }

    /**
     * Get the role section icon.
     */
    public function getRoleSectionIcon(): string | BackedEnum | Htmlable | null
    {
        if ($this->roleSectionIcon !== null) {
            /** @var string|BackedEnum|Htmlable|false $result */
            $result = $this->evaluate($this->roleSectionIcon);

            if ($result === false) {
                return null;
            }

            return $result;
        }

        /** @var string|false|null $configValue */
        $configValue = config('filament-guardian.role_resource.sections.role.icon');

        if ($configValue === false) {
            return null;
        }

        if ($configValue !== null) {
            return $configValue;
        }

        return Heroicon::OutlinedShieldCheck;
    }

    /**
     * Check if the role section should be aside.
     */
    public function isRoleSectionAside(): bool
    {
        if ($this->roleSectionAside !== null) {
            /** @var bool $result */
            $result = $this->evaluate($this->roleSectionAside);

            return $result;
        }

        /** @var bool $result */
        $result = config('filament-guardian.role_resource.sections.role.aside', false);

        return $result;
    }

    /**
     * Get the permissions section label.
     */
    public function getPermissionsSectionLabel(): string
    {
        if ($this->permissionsSectionLabel !== null) {
            /** @var string $result */
            $result = $this->evaluate($this->permissionsSectionLabel);

            return $result;
        }

        /** @var string|null $configValue */
        $configValue = config('filament-guardian.role_resource.sections.permissions.label');

        if ($configValue !== null) {
            return $configValue;
        }

        return __('filament-guardian::filament-guardian.roles.sections.permissions');
    }

    /**
     * Get the permissions section description.
     */
    public function getPermissionsSectionDescription(): ?string
    {
        if ($this->permissionsSectionDescription !== null) {
            /** @var string $result */
            $result = $this->evaluate($this->permissionsSectionDescription);

            return $result;
        }

        /** @var string|null $configValue */
        $configValue = config('filament-guardian.role_resource.sections.permissions.description');

        return $configValue;
    }

    /**
     * Get the permissions section icon.
     */
    public function getPermissionsSectionIcon(): string | BackedEnum | Htmlable | null
    {
        if ($this->permissionsSectionIcon !== null) {
            /** @var string|BackedEnum|Htmlable|false $result */
            $result = $this->evaluate($this->permissionsSectionIcon);

            if ($result === false) {
                return null;
            }

            return $result;
        }

        /** @var string|false|null $configValue */
        $configValue = config('filament-guardian.role_resource.sections.permissions.icon');

        if ($configValue === false) {
            return null;
        }

        if ($configValue !== null) {
            return $configValue;
        }

        return Heroicon::OutlinedKey;
    }

    /**
     * Check if the permissions section should be aside.
     */
    public function isPermissionsSectionAside(): bool
    {
        if ($this->permissionsSectionAside !== null) {
            /** @var bool $result */
            $result = $this->evaluate($this->permissionsSectionAside);

            return $result;
        }

        /** @var bool $result */
        $result = config('filament-guardian.role_resource.sections.permissions.aside', false);

        return $result;
    }
}
