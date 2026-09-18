<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian\Support;

use Filament\Pages\PageConfiguration;
use Filament\Panel;
use Filament\Resources\ResourceConfiguration;

// From Filament 4.8 and 5.3, anything registered through ::make() is left out of getResources() and getPages().
final class PanelComponents
{
    /**
     * @return list<class-string>
     */
    public static function resources(Panel $panel): array
    {
        // @phpstan-ignore function.alreadyNarrowedType (absent before Filament 4.8 and 5.3)
        $configured = method_exists($panel, 'getResourceConfigurations')
            ? array_map(
                fn (ResourceConfiguration $configuration): string => $configuration->resource,
                $panel->getResourceConfigurations(),
            )
            : [];

        return self::unique([...$panel->getResources(), ...$configured]);
    }

    /**
     * @return list<class-string>
     */
    public static function pages(Panel $panel): array
    {
        // @phpstan-ignore function.alreadyNarrowedType (absent before Filament 4.8 and 5.3)
        $configured = method_exists($panel, 'getPageConfigurations')
            ? array_map(
                fn (PageConfiguration $configuration): string => $configuration->page,
                $panel->getPageConfigurations(),
            )
            : [];

        return self::unique([...$panel->getPages(), ...$configured]);
    }

    /**
     * @param  array<class-string>  $classes
     * @return list<class-string>
     */
    private static function unique(array $classes): array
    {
        return array_values(array_unique($classes));
    }
}
