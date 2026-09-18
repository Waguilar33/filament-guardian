<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian\Concerns;

use BackedEnum;
use Closure;
use Filament\Resources\Pages\Enums\ContentTabPosition;
use Filament\Support\Concerns\EvaluatesClosures;

trait HasContentTabs
{
    use EvaluatesClosures;

    protected bool | Closure | null $combineRelationManagerTabsWithContent = null;

    protected bool | Closure | null $combineRelationManagerTabsWithContentOnEdit = null;

    protected bool | Closure | null $combineRelationManagerTabsWithContentOnView = null;

    protected string | Closure | null $contentTabLabel = null;

    protected string | BackedEnum | Closure | null $contentTabIcon = null;

    protected ContentTabPosition | string | Closure | null $contentTabPosition = null;

    /**
     * Shortcut for both pages. Acts as a default that per-page setters override.
     *
     * @api
     */
    public function combineRelationManagerTabsWithContent(bool | Closure $condition = true): static
    {
        $this->combineRelationManagerTabsWithContent = $condition;

        return $this;
    }

    /** @api */
    public function combineRelationManagerTabsWithContentOnEdit(bool | Closure $condition = true): static
    {
        $this->combineRelationManagerTabsWithContentOnEdit = $condition;

        return $this;
    }

    /** @api */
    public function combineRelationManagerTabsWithContentOnView(bool | Closure $condition = true): static
    {
        $this->combineRelationManagerTabsWithContentOnView = $condition;

        return $this;
    }

    /** @api */
    public function contentTabLabel(string | Closure | null $label): static
    {
        $this->contentTabLabel = $label;

        return $this;
    }

    /** @api */
    public function contentTabIcon(string | BackedEnum | Closure | null $icon): static
    {
        $this->contentTabIcon = $icon;

        return $this;
    }

    /** @api */
    public function contentTabPosition(ContentTabPosition | string | Closure | null $position): static
    {
        $this->contentTabPosition = $position;

        return $this;
    }

    public function shouldCombineRelationManagerTabsWithContent(): bool
    {
        return $this->resolveCombineFlag(null, null);
    }

    public function shouldCombineRelationManagerTabsWithContentOnEdit(): bool
    {
        return $this->resolveCombineFlag(
            $this->combineRelationManagerTabsWithContentOnEdit,
            'combine_relation_manager_tabs_on_edit',
        );
    }

    public function shouldCombineRelationManagerTabsWithContentOnView(): bool
    {
        return $this->resolveCombineFlag(
            $this->combineRelationManagerTabsWithContentOnView,
            'combine_relation_manager_tabs_on_view',
        );
    }

    /**
     * Resolution order (most specific first, fluent over config within each tier):
     *   1. per-page fluent setter
     *   2. global fluent setter (combineRelationManagerTabsWithContent)
     *   3. per-page config key
     *   4. global config key (default false)
     */
    protected function resolveCombineFlag(bool | Closure | null $perPage, ?string $perPageConfigKey): bool
    {
        if ($perPage !== null) {
            /** @var bool $result */
            $result = $this->evaluate($perPage);

            return $result;
        }

        if ($this->combineRelationManagerTabsWithContent !== null) {
            /** @var bool $result */
            $result = $this->evaluate($this->combineRelationManagerTabsWithContent);

            return $result;
        }

        if ($perPageConfigKey !== null) {
            $configValue = config("filament-guardian.role_resource.content_tabs.{$perPageConfigKey}");

            if ($configValue !== null) {
                return (bool) $configValue;
            }
        }

        /** @var bool $globalConfig */
        $globalConfig = config('filament-guardian.role_resource.content_tabs.combine_relation_manager_tabs', false);

        return $globalConfig;
    }

    public function getContentTabLabel(): ?string
    {
        if ($this->contentTabLabel !== null) {
            /** @var string|null $result */
            $result = $this->evaluate($this->contentTabLabel);

            return $result;
        }

        /** @var string|null $configValue */
        $configValue = config('filament-guardian.role_resource.content_tabs.label');

        return $configValue;
    }

    public function getContentTabIcon(): string | BackedEnum | null
    {
        if ($this->contentTabIcon !== null) {
            /** @var string|BackedEnum|null $result */
            $result = $this->evaluate($this->contentTabIcon);

            return $result;
        }

        /** @var string|null $configValue */
        $configValue = config('filament-guardian.role_resource.content_tabs.icon');

        return $configValue;
    }

    public function getContentTabPosition(): ?ContentTabPosition
    {
        $value = $this->contentTabPosition !== null
            ? $this->evaluate($this->contentTabPosition)
            : config('filament-guardian.role_resource.content_tabs.position');

        return $this->resolveContentTabPosition($value);
    }

    protected function resolveContentTabPosition(mixed $value): ?ContentTabPosition
    {
        if ($value instanceof ContentTabPosition) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            return ContentTabPosition::tryFrom($value);
        }

        return null;
    }
}
