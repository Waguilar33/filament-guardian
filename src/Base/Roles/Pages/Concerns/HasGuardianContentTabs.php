<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian\Base\Roles\Pages\Concerns;

use BackedEnum;
use Filament\Resources\Pages\Enums\ContentTabPosition;
use Waguilar\FilamentGuardian\FilamentGuardianPlugin;

trait HasGuardianContentTabs
{
    public function getContentTabLabel(): ?string
    {
        return static::guardianPlugin()?->getContentTabLabel()
            ?? parent::getContentTabLabel();
    }

    // No Htmlable and no parent call: Filament 4.0-4.2.2 declare this without Htmlable, and the parent only returns null.
    public function getContentTabIcon(): string | BackedEnum | null
    {
        return static::guardianPlugin()?->getContentTabIcon();
    }

    public function getContentTabPosition(): ?ContentTabPosition
    {
        return static::guardianPlugin()?->getContentTabPosition()
            ?? parent::getContentTabPosition();
    }

    protected static function guardianPlugin(): ?FilamentGuardianPlugin
    {
        $panel = filament()->getCurrentPanel();

        if (! $panel?->hasPlugin('filament-guardian')) {
            return null;
        }

        /** @var FilamentGuardianPlugin $plugin */
        $plugin = $panel->getPlugin('filament-guardian');

        return $plugin;
    }
}
