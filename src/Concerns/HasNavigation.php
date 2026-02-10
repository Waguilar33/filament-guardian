<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian\Concerns;

use BackedEnum;
use Closure;
use Filament\Support\Concerns\EvaluatesClosures;

trait HasNavigation
{
    use EvaluatesClosures;

    protected string | Closure | null $modelLabel = null;

    protected string | Closure | null $pluralModelLabel = null;

    protected string | Closure | null $slug = null;

    protected string | BackedEnum | Closure | null $navigationIcon = null;

    protected string | BackedEnum | Closure | null $activeNavigationIcon = null;

    protected string | Closure | null $navigationLabel = null;

    protected string | Closure | null $cluster = null;

    protected string | Closure | null $navigationGroup = null;

    protected int | Closure | null $navigationSort = null;

    protected string | Closure | null $navigationBadge = null;

    /** @var string|array<string>|Closure|null */
    protected string | array | Closure | null $navigationBadgeColor = null;

    protected string | Closure | null $navigationParentItem = null;

    protected bool | Closure $shouldRegisterNavigation = true;

    public function modelLabel(string | Closure $label): static
    {
        $this->modelLabel = $label;

        return $this;
    }

    public function pluralModelLabel(string | Closure $label): static
    {
        $this->pluralModelLabel = $label;

        return $this;
    }

    public function slug(string | Closure $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function navigationIcon(string | BackedEnum | Closure | null $icon): static
    {
        $this->navigationIcon = $icon;

        return $this;
    }

    public function activeNavigationIcon(string | BackedEnum | Closure | null $icon): static
    {
        $this->activeNavigationIcon = $icon;

        return $this;
    }

    public function navigationLabel(string | Closure | null $label): static
    {
        $this->navigationLabel = $label;

        return $this;
    }

    /** @api */
    public function cluster(string | Closure | null $cluster): static
    {
        $this->cluster = $cluster;

        return $this;
    }

    public function navigationGroup(string | Closure | null $group): static
    {
        $this->navigationGroup = $group;

        return $this;
    }

    public function navigationSort(int | Closure | null $sort): static
    {
        $this->navigationSort = $sort;

        return $this;
    }

    public function navigationBadge(string | Closure | null $badge): static
    {
        $this->navigationBadge = $badge;

        return $this;
    }

    /**
     * @param  string|array<string>|Closure|null  $color
     */
    public function navigationBadgeColor(string | array | Closure | null $color): static
    {
        $this->navigationBadgeColor = $color;

        return $this;
    }

    public function navigationParentItem(string | Closure | null $item): static
    {
        $this->navigationParentItem = $item;

        return $this;
    }

    public function registerNavigation(bool | Closure $condition = true): static
    {
        $this->shouldRegisterNavigation = $condition;

        return $this;
    }

    public function getModelLabel(): ?string
    {
        if ($this->modelLabel !== null) {
            /** @var string $result */
            $result = $this->evaluate($this->modelLabel);

            return $result;
        }

        /** @var string|null $configValue */
        $configValue = config('filament-guardian.role_resource.model_label');

        if ($configValue !== null) {
            return $configValue;
        }

        $translationKey = 'filament-guardian::filament-guardian.roles.label';
        $translated = __($translationKey);

        if ($translated !== $translationKey) {
            return $translated;
        }

        return null;
    }

    public function getPluralModelLabel(): ?string
    {
        if ($this->pluralModelLabel !== null) {
            /** @var string $result */
            $result = $this->evaluate($this->pluralModelLabel);

            return $result;
        }

        /** @var string|null $configValue */
        $configValue = config('filament-guardian.role_resource.plural_model_label');

        if ($configValue !== null) {
            return $configValue;
        }

        $translationKey = 'filament-guardian::filament-guardian.roles.plural';
        $translated = __($translationKey);

        if ($translated !== $translationKey) {
            return $translated;
        }

        return null;
    }

    public function getSlug(): ?string
    {
        if ($this->slug !== null) {
            /** @var string $result */
            $result = $this->evaluate($this->slug);

            return $result;
        }

        /** @var string|null $configValue */
        $configValue = config('filament-guardian.role_resource.slug');

        return $configValue;
    }

    public function getNavigationIcon(): string | BackedEnum | null
    {
        if ($this->navigationIcon !== null) {
            /** @var string|BackedEnum|null $result */
            $result = $this->evaluate($this->navigationIcon);

            return $result;
        }

        /** @var string|null $configValue */
        $configValue = config('filament-guardian.role_resource.navigation.icon');

        return $configValue;
    }

    public function getActiveNavigationIcon(): string | BackedEnum | null
    {
        if ($this->activeNavigationIcon !== null) {
            /** @var string|BackedEnum|null $result */
            $result = $this->evaluate($this->activeNavigationIcon);

            return $result;
        }

        /** @var string|null $configValue */
        $configValue = config('filament-guardian.role_resource.navigation.active_icon');

        return $configValue;
    }

    public function getNavigationLabel(): ?string
    {
        if ($this->navigationLabel !== null) {
            /** @var string|null $result */
            $result = $this->evaluate($this->navigationLabel);

            return $result;
        }

        /** @var string|null $configValue */
        $configValue = config('filament-guardian.role_resource.navigation.label');

        return $configValue;
    }

    /**
     * @return class-string<\Filament\Clusters\Cluster>|null
     */
    public function getCluster(): ?string
    {
        if ($this->cluster !== null) {
            /** @var class-string<\Filament\Clusters\Cluster>|null $result */
            $result = $this->evaluate($this->cluster);

            return $result;
        }

        /** @var class-string<\Filament\Clusters\Cluster>|null $configValue */
        $configValue = config('filament-guardian.role_resource.navigation.cluster');

        return $configValue;
    }

    public function getNavigationGroup(): ?string
    {
        if ($this->navigationGroup !== null) {
            /** @var string|null $result */
            $result = $this->evaluate($this->navigationGroup);

            return $result;
        }

        /** @var string|null $configValue */
        $configValue = config('filament-guardian.role_resource.navigation.group');

        return $configValue;
    }

    public function getNavigationSort(): ?int
    {
        if ($this->navigationSort !== null) {
            /** @var int|null $result */
            $result = $this->evaluate($this->navigationSort);

            return $result;
        }

        /** @var int|null $configValue */
        $configValue = config('filament-guardian.role_resource.navigation.sort');

        return $configValue;
    }

    public function getNavigationBadge(): ?string
    {
        if ($this->navigationBadge !== null) {
            /** @var string|null $result */
            $result = $this->evaluate($this->navigationBadge);

            return $result;
        }

        /** @var string|null $configValue */
        $configValue = config('filament-guardian.role_resource.navigation.badge');

        return $configValue;
    }

    /**
     * @return string|array<string>|null
     */
    public function getNavigationBadgeColor(): string | array | null
    {
        if ($this->navigationBadgeColor !== null) {
            /** @var string|array<string>|null $result */
            $result = $this->evaluate($this->navigationBadgeColor);

            return $result;
        }

        /** @var string|array<string>|null $configValue */
        $configValue = config('filament-guardian.role_resource.navigation.badge_color');

        return $configValue;
    }

    public function getNavigationParentItem(): ?string
    {
        if ($this->navigationParentItem !== null) {
            /** @var string|null $result */
            $result = $this->evaluate($this->navigationParentItem);

            return $result;
        }

        /** @var string|null $configValue */
        $configValue = config('filament-guardian.role_resource.navigation.parent_item');

        return $configValue;
    }

    public function shouldRegisterNavigation(): bool
    {
        if ($this->shouldRegisterNavigation !== true) {
            /** @var bool $result */
            $result = $this->evaluate($this->shouldRegisterNavigation);

            return $result;
        }

        /** @var bool $configValue */
        $configValue = config('filament-guardian.role_resource.navigation.register', true);

        return $configValue;
    }
}
