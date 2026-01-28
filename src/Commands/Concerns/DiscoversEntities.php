<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian\Commands\Concerns;

use Filament\Panel;
use Filament\Resources\Resource;
use Filament\Widgets\WidgetConfiguration;

trait DiscoversEntities
{
    use ReadsResourceConfig;

    /**
     * Get all resources from a panel, excluding configured exclusions.
     *
     * @return array<int, class-string<resource>>
     */
    protected function getResources(Panel $panel): array
    {
        /** @var array<class-string<resource>> $excluded */
        $excluded = config('filament-guardian.resources.exclude', []);

        /** @var array<int, class-string<resource>> $resources */
        $resources = collect($panel->getResources())
            ->reject(fn (string $resource): bool => in_array($resource, $excluded, true))
            ->values()
            ->all();

        return $resources;
    }

    /**
     * Get all pages from a panel, excluding configured exclusions.
     *
     * @return array<int, class-string>
     */
    protected function getPages(Panel $panel): array
    {
        /** @var array<class-string> $excluded */
        $excluded = config('filament-guardian.pages.exclude', []);

        /** @var array<int, class-string> $pages */
        $pages = collect($panel->getPages())
            ->reject(fn (string $page): bool => in_array($page, $excluded, true))
            ->values()
            ->all();

        return $pages;
    }

    /**
     * Get all widgets from a panel, excluding configured exclusions.
     *
     * @return array<int, class-string>
     */
    protected function getWidgets(Panel $panel): array
    {
        /** @var array<class-string> $excluded */
        $excluded = config('filament-guardian.widgets.exclude', []);

        /** @var array<int, class-string> $widgets */
        $widgets = collect($panel->getWidgets())
            ->map(function (string | WidgetConfiguration $widget): string {
                if ($widget instanceof WidgetConfiguration) {
                    return $widget->widget;
                }

                return $widget;
            })
            ->reject(fn (string $widget): bool => in_array($widget, $excluded, true))
            ->values()
            ->all();

        return $widgets;
    }

    /**
     * Get the subject name for a resource.
     */
    protected function getResourceSubject(string $resourceClass): string
    {
        /** @var string $subjectType */
        $subjectType = config('filament-guardian.resources.subject', 'model');

        if ($subjectType === 'class') {
            return class_basename($resourceClass);
        }

        /** @var class-string<resource> $resourceClass */
        return class_basename($resourceClass::getModel());
    }

    /**
     * Get the subject name for a page.
     */
    protected function getPageSubject(string $pageClass): string
    {
        return class_basename($pageClass);
    }

    /**
     * Get the subject name for a widget.
     */
    protected function getWidgetSubject(string $widgetClass): string
    {
        return class_basename($widgetClass);
    }

    /**
     * Get the prefix/action for pages.
     */
    protected function getPagePrefix(): string
    {
        /** @var string $prefix */
        $prefix = config('filament-guardian.pages.prefix', 'view');

        return $prefix;
    }

    /**
     * Get the prefix/action for widgets.
     */
    protected function getWidgetPrefix(): string
    {
        /** @var string $prefix */
        $prefix = config('filament-guardian.widgets.prefix', 'view');

        return $prefix;
    }
}
