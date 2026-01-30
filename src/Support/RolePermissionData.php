<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian\Support;

use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use RuntimeException;
use Spatie\Permission\Contracts\Role;
use Spatie\Permission\Models\Permission;
use Waguilar\FilamentGuardian\Contracts\PermissionKeyBuilder as PermissionKeyBuilderContract;
use Waguilar\FilamentGuardian\FilamentGuardianPlugin;

/**
 * Value object that encapsulates all permission data needed for the Role form/infolist.
 *
 * This class separates business logic (permission resolution, categorization, options building)
 * from UI concerns (form/infolist components).
 *
 * Uses request-level caching to avoid repeated expensive initialization.
 */
final class RolePermissionData
{
    /**
     * Request-level cache for RolePermissionData instances keyed by panel ID.
     *
     * @var array<string, self>
     */
    private static array $cache = [];

    private PermissionResolver $resolver;

    private PermissionLabelResolver $labelResolver;

    private PermissionKeyBuilderContract $keyBuilder;

    /** @var Collection<string, Collection<int, string>> */
    private Collection $resourcePermissions;

    /** @var Collection<string, string> */
    private Collection $resourceLabels;

    /** @var Collection<string, string|null> */
    private Collection $resourceIcons;

    /** @var Collection<int, string> */
    private Collection $pagePermissions;

    /** @var Collection<string, string> */
    private Collection $pageLabels;

    /** @var Collection<int, string> */
    private Collection $widgetPermissions;

    /** @var Collection<string, string> */
    private Collection $widgetLabels;

    /** @var Collection<int, string> */
    private Collection $customPermissions;

    private function __construct(
        private readonly FilamentGuardianPlugin $plugin,
        Panel $panel,
    ) {
        $this->keyBuilder = $plugin->getKeyBuilder();
        $this->resolver = new PermissionResolver($panel, $panel->getAuthGuard(), $this->keyBuilder);
        $this->labelResolver = new PermissionLabelResolver($this->keyBuilder);

        $this->resourcePermissions = $this->resolver->getResourcePermissions();
        $this->resourceLabels = $this->resolver->getResourceLabels();
        $this->resourceIcons = $this->resolver->getResourceIcons();
        $this->pagePermissions = $this->resolver->getPagePermissions();
        $this->pageLabels = $this->resolver->getPageLabels();
        $this->widgetPermissions = $this->resolver->getWidgetPermissions();
        $this->widgetLabels = $this->resolver->getWidgetLabels();
        $this->customPermissions = $this->resolver->getCustomPermissions();
    }

    /**
     * Create from the current panel context with request-level caching.
     *
     * The same instance is returned for the same panel within a request,
     * avoiding repeated expensive permission resolution operations.
     */
    public static function make(): self
    {
        $panel = Filament::getCurrentPanel() ?? throw new RuntimeException('No Filament panel is currently active.');
        $panelId = $panel->getId();

        if (! isset(self::$cache[$panelId])) {
            self::$cache[$panelId] = new self(FilamentGuardianPlugin::get(), $panel);
        }

        return self::$cache[$panelId];
    }

    /**
     * Clear the request-level cache.
     *
     * Useful for testing or when permissions are modified during a request.
     *
     * @api
     */
    public static function clearCache(): void
    {
        self::$cache = [];
    }

    /**
     * Check if there are any permissions to display.
     */
    public function hasPermissions(): bool
    {
        return $this->hasResources() || $this->hasPages() || $this->hasWidgets() || $this->hasCustom();
    }

    /**
     * Check if there are resource permissions.
     */
    public function hasResources(): bool
    {
        return $this->plugin->shouldShowResourcesTab() && $this->resourcePermissions->isNotEmpty();
    }

    /**
     * Check if there are page permissions.
     */
    public function hasPages(): bool
    {
        return $this->plugin->shouldShowPagesTab() && $this->pagePermissions->isNotEmpty();
    }

    /**
     * Check if there are widget permissions.
     */
    public function hasWidgets(): bool
    {
        return $this->plugin->shouldShowWidgetsTab() && $this->widgetPermissions->isNotEmpty();
    }

    /**
     * Check if there are custom permissions.
     */
    public function hasCustom(): bool
    {
        return $this->plugin->shouldShowCustomPermissionsTab() && $this->customPermissions->isNotEmpty();
    }

    /**
     * @return Collection<string, array{permissions: Collection<int, string>, label: string, icon: string|null, options: array<string, string>}>
     */
    public function getResources(): Collection
    {
        /** @var array<string, array{permissions: Collection<int, string>, label: string, icon: string|null, options: array<string, string>}> $result */
        $result = [];

        foreach ($this->resourcePermissions as $subject => $permissions) {
            $result[$subject] = [
                'permissions' => $permissions,
                'label' => $this->resourceLabels->get($subject, $subject),
                'icon' => $this->resourceIcons->get($subject),
                'options' => $this->buildShortLabelOptions($permissions),
            ];
        }

        return new Collection($result);
    }

    /**
     * Get the total count of resource permissions.
     */
    public function getResourcePermissionCount(): int
    {
        return $this->resourcePermissions->flatten()->count();
    }

    /**
     * Get page permissions with options.
     *
     * @return array{permissions: Collection<int, string>, options: array<string, string>}
     */
    public function getPages(): array
    {
        return [
            'permissions' => $this->pagePermissions,
            'options' => $this->buildEntityOptions($this->pagePermissions, $this->pageLabels),
        ];
    }

    /**
     * Get widget permissions with options.
     *
     * @return array{permissions: Collection<int, string>, options: array<string, string>}
     */
    public function getWidgets(): array
    {
        return [
            'permissions' => $this->widgetPermissions,
            'options' => $this->buildEntityOptions($this->widgetPermissions, $this->widgetLabels),
        ];
    }

    /**
     * Get custom permissions with options.
     *
     * @return array{permissions: Collection<int, string>, options: array<string, string>}
     */
    public function getCustom(): array
    {
        return [
            'permissions' => $this->customPermissions,
            'options' => $this->buildFullLabelOptions($this->customPermissions),
        ];
    }

    /**
     * Filter permissions by those assigned to a role.
     *
     * @api
     *
     * @param  Collection<int, string>  $permissions
     * @param  Collection<int, string>  $rolePermissions
     * @return Collection<int, string>
     */
    public function filterAssigned(Collection $permissions, Collection $rolePermissions): Collection
    {
        return $permissions->intersect($rolePermissions);
    }

    /**
     * @param  Collection<int, string>  $rolePermissions
     * @return Collection<string, array{label: string, icon: string|null, permissions: array<int, string>}>
     */
    public function getAssignedResources(Collection $rolePermissions): Collection
    {
        /** @var array<string, array{label: string, icon: string|null, permissions: array<int, string>}> $result */
        $result = [];

        foreach ($this->resourcePermissions as $subject => $permissions) {
            $assigned = $permissions->intersect($rolePermissions);

            if ($assigned->isEmpty()) {
                continue;
            }

            $result[$subject] = [
                'label' => $this->resourceLabels->get($subject, $subject),
                'icon' => $this->resourceIcons->get($subject),
                'permissions' => $assigned
                    ->map(fn (string $p): string => $this->labelResolver->getShortLabel($p))
                    ->values()
                    ->all(),
            ];
        }

        return new Collection($result);
    }

    /**
     * Get assigned page labels for a role.
     *
     * @param  Collection<int, string>  $rolePermissions
     * @return array<int, string>
     */
    public function getAssignedPageLabels(Collection $rolePermissions): array
    {
        return $this->pagePermissions
            ->intersect($rolePermissions)
            ->map(function (string $permission): string {
                $subject = $this->keyBuilder->extractSubject($permission);

                return $this->pageLabels->get($subject, $subject);
            })
            ->values()
            ->all();
    }

    /**
     * Get assigned widget labels for a role.
     *
     * @param  Collection<int, string>  $rolePermissions
     * @return array<int, string>
     */
    public function getAssignedWidgetLabels(Collection $rolePermissions): array
    {
        return $this->widgetPermissions
            ->intersect($rolePermissions)
            ->map(function (string $permission): string {
                $subject = $this->keyBuilder->extractSubject($permission);

                return $this->widgetLabels->get($subject, $subject);
            })
            ->values()
            ->all();
    }

    /**
     * Get assigned custom permission labels for a role.
     *
     * @param  Collection<int, string>  $rolePermissions
     * @return array<int, string>
     */
    public function getAssignedCustomLabels(Collection $rolePermissions): array
    {
        return $this->customPermissions
            ->intersect($rolePermissions)
            ->map(fn (string $p): string => $this->labelResolver->getLabel($p))
            ->values()
            ->all();
    }

    /**
     * Count assigned permissions by type for a role.
     *
     * @param  Collection<int, string>  $rolePermissions
     * @return array{resources: int, pages: int, widgets: int, custom: int}
     */
    public function countAssigned(Collection $rolePermissions): array
    {
        return [
            'resources' => $this->resourcePermissions->flatten()->intersect($rolePermissions)->count(),
            'pages' => $this->pagePermissions->intersect($rolePermissions)->count(),
            'widgets' => $this->widgetPermissions->intersect($rolePermissions)->count(),
            'custom' => $this->customPermissions->intersect($rolePermissions)->count(),
        ];
    }

    /**
     * Get role's permission names as a collection.
     *
     * @param  Role&Model  $role
     * @return Collection<int, string>
     */
    public function getRolePermissions(Role $role): Collection
    {
        /** @noinspection PhpUndefinedFieldInspection - permissions is a Spatie HasPermissions trait relationship */
        /** @var \Illuminate\Database\Eloquent\Collection<int, Permission> $rolePermissions */
        $rolePermissions = $role->permissions;

        /** @var Collection<int, string> $permissions */
        $permissions = collect($rolePermissions->pluck('name'));

        return $permissions;
    }

    /**
     * Build options with short labels (action only).
     *
     * @param  Collection<int, string>  $permissions
     * @return array<string, string>
     */
    private function buildShortLabelOptions(Collection $permissions): array
    {
        $options = [];

        foreach ($permissions as $permission) {
            $options[$permission] = $this->labelResolver->getShortLabel($permission);
        }

        return $options;
    }

    /**
     * Build options with full labels.
     *
     * @param  Collection<int, string>  $permissions
     * @return array<string, string>
     */
    private function buildFullLabelOptions(Collection $permissions): array
    {
        $options = [];

        foreach ($permissions as $permission) {
            $options[$permission] = $this->labelResolver->getLabel($permission);
        }

        return $options;
    }

    /**
     * Build options from entity labels (pages/widgets).
     *
     * @param  Collection<int, string>  $permissions
     * @param  Collection<string, string>  $labels
     * @return array<string, string>
     */
    private function buildEntityOptions(Collection $permissions, Collection $labels): array
    {
        $options = [];

        foreach ($permissions as $permission) {
            $subject = $this->keyBuilder->extractSubject($permission);
            $options[$permission] = $labels->get($subject, $subject);
        }

        return $options;
    }
}
