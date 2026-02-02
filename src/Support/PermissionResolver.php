<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian\Support;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Resources\Resource;
use Filament\Widgets\WidgetConfiguration;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use ReflectionMethod;
use ReflectionProperty;
use Spatie\Permission\Models\Permission;
use Waguilar\FilamentGuardian\Contracts\PermissionKeyBuilder as PermissionKeyBuilderContract;

final class PermissionResolver
{
    /**
     * Cache of categorized permissions.
     *
     * @var array{
     *     resources: Collection<string, Collection<int, string>>,
     *     pages: Collection<int, string>,
     *     widgets: Collection<int, string>,
     *     custom: Collection<int, string>,
     * }|null
     */
    private ?array $categorized = null;

    /**
     * Cache of all permissions from database.
     *
     * @var Collection<int, string>|null
     */
    private ?Collection $allPermissions = null;

    /**
     * Cache of widget labels.
     *
     * @var Collection<string, string>|null
     */
    private ?Collection $widgetLabelsCache = null;

    public function __construct(
        private readonly Panel $panel,
        private readonly string $guard,
        private readonly PermissionKeyBuilderContract $keyBuilder,
    ) {}

    /**
     * Get all permissions grouped by resource subject.
     *
     * @return Collection<string, Collection<int, string>>
     */
    public function getResourcePermissions(): Collection
    {
        $this->categorize();

        /** @var Collection<string, Collection<int, string>> $resources */
        $resources = $this->categorized['resources'] ?? collect();

        return $resources;
    }

    /**
     * Get all page permissions.
     *
     * @return Collection<int, string>
     */
    public function getPagePermissions(): Collection
    {
        $this->categorize();

        /** @var Collection<int, string> $pages */
        $pages = $this->categorized['pages'] ?? collect();

        return $pages;
    }

    /**
     * Get all widget permissions.
     *
     * @return Collection<int, string>
     */
    public function getWidgetPermissions(): Collection
    {
        $this->categorize();

        /** @var Collection<int, string> $widgets */
        $widgets = $this->categorized['widgets'] ?? collect();

        return $widgets;
    }

    /**
     * Get all custom (uncategorized) permissions.
     *
     * @return Collection<int, string>
     */
    public function getCustomPermissions(): Collection
    {
        $this->categorize();

        /** @var Collection<int, string> $custom */
        $custom = $this->categorized['custom'] ?? collect();

        return $custom;
    }

    /**
     * Get all permissions from database for the current guard.
     *
     * Results are cached within the instance to avoid repeated queries.
     *
     * @return Collection<int, string>
     */
    public function getAllPermissions(): Collection
    {
        if ($this->allPermissions !== null) {
            return $this->allPermissions;
        }

        /** @var Collection<int, string> $permissions */
        $permissions = Permission::query()
            ->whereRaw('guard_name = ?', [$this->guard])
            ->pluck('name'); // @phpstan-ignore argument.type

        $this->allPermissions = $permissions;

        return $permissions;
    }

    /**
     * Get resource labels keyed by subject.
     *
     * @return Collection<string, string>
     */
    public function getResourceLabels(): Collection
    {
        $subjects = $this->getResourceSubjects();

        /** @var Collection<string, string> $labels */
        $labels = collect($subjects)->map(function (string $resourceClass): string {
            /** @var class-string<resource> $resourceClass */
            return $resourceClass::getPluralModelLabel();
        });

        return $labels;
    }

    /**
     * @return Collection<string, string|null>
     */
    public function getResourceIcons(): Collection
    {
        $subjects = $this->getResourceSubjects();

        /** @var array<string, string|null> $icons */
        $icons = [];
        foreach ($subjects as $subject => $resourceClass) {
            /** @var class-string<resource> $resourceClass */
            $icon = $resourceClass::getNavigationIcon();

            if ($icon instanceof BackedEnum) {
                $icons[$subject] = (string) $icon->value;
            } elseif ($icon instanceof Htmlable) {
                $icons[$subject] = null;
            } else {
                $icons[$subject] = $icon;
            }
        }

        return new Collection($icons);
    }

    /**
     * Get page labels keyed by subject.
     *
     * @return Collection<string, string>
     */
    public function getPageLabels(): Collection
    {
        $subjects = $this->getPageSubjects();

        /** @var Collection<string, string> $labels */
        $labels = collect($subjects)->map(function (string $pageClass): string {
            /** @var class-string<Page> $pageClass */
            return $pageClass::getNavigationLabel();
        });

        return $labels;
    }

    /**
     * Get widget labels keyed by subject.
     *
     * Results are cached within the instance to avoid repeated expensive widget instantiation.
     *
     * @return Collection<string, string>
     */
    public function getWidgetLabels(): Collection
    {
        if ($this->widgetLabelsCache !== null) {
            return $this->widgetLabelsCache;
        }

        $subjects = $this->getWidgetSubjects();

        /** @var Collection<string, string> $labels */
        $labels = collect($subjects)->map(function (string $widgetClass): string {
            return $this->resolveWidgetLabel($widgetClass);
        });

        $this->widgetLabelsCache = $labels;

        return $labels;
    }

    /**
     * Resolve a widget's label without instantiation when possible.
     *
     * @param  class-string  $widgetClass
     */
    private function resolveWidgetLabel(string $widgetClass): string
    {
        // Try to get heading from static property or method first (avoids instantiation)
        if (method_exists($widgetClass, 'getHeading') && (new ReflectionMethod($widgetClass, 'getHeading'))->isStatic()) {
            /** @var mixed $heading */
            $heading = $widgetClass::getHeading();

            if (filled($heading) && is_string($heading)) {
                return $heading;
            }
        }

        // Check for static $heading property
        if (property_exists($widgetClass, 'heading')) {
            $reflection = new ReflectionProperty($widgetClass, 'heading');
            if ($reflection->isStatic()) {
                $heading = $reflection->getValue();

                if (filled($heading) && is_string($heading)) {
                    return $heading;
                }
            }
        }

        // Fall back to instantiation only if necessary
        /** @var object $widget */
        $widget = app($widgetClass);

        if (method_exists($widget, 'getHeading')) {
            /** @var mixed $heading */
            $heading = $widget->getHeading();

            if (filled($heading) && is_string($heading)) {
                return $heading;
            }

            if ($heading instanceof Htmlable) {
                return $heading->toHtml();
            }
        }

        // Generate label from class name as last resort
        return str(class_basename($widgetClass))
            ->kebab()
            ->replace('-', ' ')
            ->title()
            ->toString();
    }

    /**
     * Categorize all permissions into resources, pages, widgets, and custom.
     */
    private function categorize(): void
    {
        if ($this->categorized !== null) {
            return;
        }

        /** @var array<string, Collection<int, string>> $resources */
        $resources = [];

        /** @var Collection<int, string> $pages */
        $pages = collect();

        /** @var Collection<int, string> $widgets */
        $widgets = collect();

        /** @var Collection<int, string> $custom */
        $custom = collect();

        $resourceSubjects = $this->getResourceSubjects();
        $pageSubjects = $this->getPageSubjects();
        $widgetSubjects = $this->getWidgetSubjects();

        $separator = $this->keyBuilder->getSeparator();

        foreach ($this->getAllPermissions() as $permissionName) {
            if ($separator === '') {
                $custom->push($permissionName);

                continue;
            }

            $parts = explode($separator, $permissionName, 2);

            if (count($parts) !== 2) {
                // No separator found, treat as custom permission
                $custom->push($permissionName);

                continue;
            }

            $subject = $parts[1];

            // Check if it matches a resource subject
            if (isset($resourceSubjects[$subject])) {
                if (! isset($resources[$subject])) {
                    /** @var Collection<int, string> $emptyCollection */
                    $emptyCollection = collect();
                    $resources[$subject] = $emptyCollection;
                }
                $resources[$subject]->push($permissionName);

                continue;
            }

            // Check if it matches a page subject
            if (isset($pageSubjects[$subject])) {
                $pages->push($permissionName);

                continue;
            }

            // Check if it matches a widget subject
            if (isset($widgetSubjects[$subject])) {
                $widgets->push($permissionName);

                continue;
            }

            // Unmatched - treat as custom
            $custom->push($permissionName);
        }

        $this->categorized = [
            'resources' => collect($resources),
            'pages' => $pages,
            'widgets' => $widgets,
            'custom' => $custom,
        ];
    }

    /**
     * Get resource subjects keyed by formatted subject name.
     *
     * @return array<string, class-string<resource>>
     */
    private function getResourceSubjects(): array
    {
        /** @var array<class-string> $excluded */
        $excluded = config('filament-guardian.resources.exclude', []);

        /** @var string $subjectType */
        $subjectType = config('filament-guardian.resources.subject', 'model');

        $subjects = [];

        foreach ($this->panel->getResources() as $resource) {
            if (in_array($resource, $excluded, true)) {
                continue;
            }

            /** @var class-string<resource> $resource */
            if ($subjectType === 'class') {
                $subject = class_basename($resource);
            } else {
                $subject = class_basename($resource::getModel());
            }

            $formattedSubject = $this->keyBuilder->format($subject);
            $subjects[$formattedSubject] = $resource;
        }

        return $subjects;
    }

    /**
     * Get page subjects keyed by formatted subject name.
     *
     * @return array<string, class-string>
     */
    private function getPageSubjects(): array
    {
        /** @var array<class-string> $excluded */
        $excluded = config('filament-guardian.pages.exclude', []);

        $subjects = [];

        foreach ($this->panel->getPages() as $page) {
            if (in_array($page, $excluded, true)) {
                continue;
            }

            $subject = class_basename($page);
            $formattedSubject = $this->keyBuilder->format($subject);
            $subjects[$formattedSubject] = $page;
        }

        return $subjects;
    }

    /**
     * Get widget subjects keyed by formatted subject name.
     *
     * @return array<string, class-string>
     */
    private function getWidgetSubjects(): array
    {
        /** @var array<class-string> $excluded */
        $excluded = config('filament-guardian.widgets.exclude', []);

        $subjects = [];

        foreach ($this->panel->getWidgets() as $widget) {
            if ($widget instanceof WidgetConfiguration) {
                $widgetClass = $widget->widget;
            } else {
                $widgetClass = $widget;
            }

            if (in_array($widgetClass, $excluded, true)) {
                continue;
            }

            $subject = class_basename($widgetClass);
            $formattedSubject = $this->keyBuilder->format($subject);
            $subjects[$formattedSubject] = $widgetClass;
        }

        return $subjects;
    }
}
