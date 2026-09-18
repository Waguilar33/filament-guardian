<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian\Commands\Concerns;

use Filament\Panel;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Resource;
use Filament\Widgets\WidgetConfiguration;
use Throwable;
use Waguilar\FilamentGuardian\Support\PanelComponents;
use Waguilar\FilamentGuardian\Support\RelationManagerDiscoverer;
use Waguilar\FilamentGuardian\Support\RelationManagerPolicyDetector;
use Waguilar\FilamentGuardian\Support\ResourcePolicyDetector;

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
        $resources = collect(PanelComponents::resources($panel))
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
        $pages = collect(PanelComponents::pages($panel))
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
     * @return array<class-string<RelationManager>, array{class: class-string<RelationManager>, related_model: class-string, parent_resource: class-string<resource>}>
     */
    protected function getRelationManagers(Panel $panel): array
    {
        /** @var array<class-string<RelationManager>> $excluded */
        $excluded = config('filament-guardian.relation_managers.exclude', []);

        $resources = $this->getResources($panel);
        $resourceModels = $this->buildResourceModelSet($resources);

        $entries = [];

        foreach ($resources as $resourceClass) {
            foreach (RelationManagerDiscoverer::collectClasses($resourceClass) as $rmClass) {
                if (isset($entries[$rmClass]) || in_array($rmClass, $excluded, true)) {
                    continue;
                }

                if (! RelationManagerDiscoverer::isEligible($rmClass)) {
                    continue;
                }

                $modelClass = RelationManagerDiscoverer::resolveRelatedModel($rmClass, $resourceClass);

                if ($modelClass === null) {
                    continue;
                }

                if (! RelationManagerPolicyDetector::usesRelationManagerPolicy($rmClass)
                    && isset($resourceModels[$modelClass])
                ) {
                    continue;
                }

                $entries[$rmClass] = [
                    'class' => $rmClass,
                    'related_model' => $modelClass,
                    'parent_resource' => $resourceClass,
                ];
            }
        }

        return $entries;
    }

    /**
     * @param  array<int, class-string<resource>>  $resources
     * @return array<class-string, true>
     */
    protected function buildResourceModelSet(array $resources): array
    {
        $models = [];

        foreach ($resources as $resourceClass) {
            try {
                /** @var class-string $modelClass */
                $modelClass = $resourceClass::getModel();
            } catch (Throwable) {
                continue;
            }

            if (class_exists($modelClass)) {
                $models[$modelClass] = true;
            }
        }

        return $models;
    }

    /**
     * Get the subject name for a resource.
     */
    protected function getResourceSubject(string $resourceClass): string
    {
        if (ResourcePolicyDetector::usesResourcePolicy($resourceClass)) {
            return ResourcePolicyDetector::getResourceSubject($resourceClass);
        }

        /** @var string $subjectType */
        $subjectType = config('filament-guardian.resources.subject', 'model');

        if ($subjectType === 'class') {
            return class_basename($resourceClass);
        }

        /** @var class-string<resource> $resourceClass */
        return class_basename($resourceClass::getModel());
    }

    /**
     * @param  class-string<RelationManager>  $rmClass
     * @param  class-string  $modelClass
     */
    protected function getRelationManagerSubject(string $rmClass, string $modelClass): string
    {
        if (RelationManagerPolicyDetector::usesRelationManagerPolicy($rmClass)) {
            return RelationManagerPolicyDetector::getRelationManagerSubject($rmClass);
        }

        $managed = $this->getManagedRelationManagerConfig($rmClass);

        if (isset($managed['subject']) && is_string($managed['subject'])) {
            return $managed['subject'];
        }

        /** @var string $subjectType */
        $subjectType = config('filament-guardian.relation_managers.subject', 'model');

        if ($subjectType === 'class') {
            return RelationManagerPolicyDetector::getRelationManagerSubject($rmClass);
        }

        return class_basename($modelClass);
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
