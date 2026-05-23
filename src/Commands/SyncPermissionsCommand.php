<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian\Commands;

use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Spatie\Permission\Contracts\Permission;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\Console\Attribute\AsCommand;
use Waguilar\FilamentGuardian\Commands\Concerns\CreatesPermissions;
use Waguilar\FilamentGuardian\Commands\Concerns\DiscoversEntities;

#[AsCommand(name: 'guardian:sync', description: 'Sync permissions for all Filament panels')]
class SyncPermissionsCommand extends Command
{
    use CreatesPermissions;
    use DiscoversEntities;

    /** @var string */
    public $signature = 'guardian:sync
        {--panel=* : Specific panel IDs to sync (syncs all if not specified)}
        {--no-relation-managers : Skip auto-discovered relation managers}
        {--prune : Delete permissions for the synced guard(s) that this run did not produce (destructive)}';

    /** @var string */
    public $description = 'Sync permissions for all Filament panels';

    /** @var array<string, array{created: int, existing: int, deleted: int}> */
    protected array $stats = [];

    /**
     * Permission names produced by this run, keyed by guard.
     *
     * @var array<string, array<string, true>>
     */
    protected array $expected = [];

    public function handle(): int
    {
        $panels = $this->getPanelsToSync();

        if ($panels === []) {
            $this->components->warn('No panels found to sync.');

            return self::SUCCESS;
        }

        $this->components->info('Syncing permissions for ' . count($panels) . ' panel(s)...');
        $this->newLine();

        foreach ($panels as $panel) {
            $this->syncPanel($panel);
        }

        $this->syncCustomPermissions($panels);

        if ($this->option('prune')) {
            $this->prunePermissions($panels);
        }

        $this->displaySummary();

        return self::SUCCESS;
    }

    /**
     * @return array<Panel>
     */
    protected function getPanelsToSync(): array
    {
        /** @var array<string> $requestedPanels */
        $requestedPanels = $this->option('panel');

        $allPanels = Filament::getPanels();

        if ($requestedPanels === []) {
            return array_values($allPanels);
        }

        return collect($allPanels)
            ->filter(fn (Panel $panel): bool => in_array($panel->getId(), $requestedPanels, true))
            ->values()
            ->all();
    }

    protected function syncPanel(Panel $panel): void
    {
        Filament::setCurrentPanel($panel);

        $guard = $panel->getAuthGuard();
        $panelId = $panel->getId();

        $this->components->twoColumnDetail(
            "<fg=bright-blue>Panel:</> {$panelId}",
            "<fg=gray>Guard:</> {$guard}"
        );

        $this->validateGuard($guard);

        $this->stats[$guard] ??= ['created' => 0, 'existing' => 0, 'deleted' => 0];

        $this->syncResources($panel, $guard);
        $this->syncRelationManagers($panel, $guard);
        $this->syncPages($panel, $guard);
        $this->syncWidgets($panel, $guard);

        $this->newLine();
    }

    protected function syncResources(Panel $panel, string $guard): void
    {
        $resources = $this->getResources($panel);

        if ($resources === []) {
            return;
        }

        foreach ($resources as $resourceClass) {
            $subject = $this->getResourceSubject($resourceClass);
            $methods = $this->getResourceMethods($resourceClass);
            $permissionKeys = $this->buildResourcePermissionKeys($subject, $methods);

            foreach ($permissionKeys as $key) {
                $this->syncPermission($key, $guard);
            }
        }

        $this->components->twoColumnDetail(
            '  Resources',
            '<fg=gray>' . count($resources) . ' resource(s), ' . count($resources) * count($this->getResourceMethods($resources[0] ?? '')) . ' permission(s)</>'
        );
    }

    protected function syncRelationManagers(Panel $panel, string $guard): void
    {
        if ($this->option('no-relation-managers')) {
            return;
        }

        $entries = $this->getRelationManagers($panel);

        if ($entries === []) {
            return;
        }

        $totalPermissions = 0;

        foreach ($entries as $entry) {
            $rmClass = $entry['class'];
            $modelClass = $entry['related_model'];
            $subject = $this->getRelationManagerSubject($rmClass, $modelClass);
            $methods = $this->getRelationManagerMethods($rmClass);
            $permissionKeys = $this->buildResourcePermissionKeys($subject, $methods);

            foreach ($permissionKeys as $key) {
                $this->syncPermission($key, $guard);
                $totalPermissions++;
            }
        }

        $this->components->twoColumnDetail(
            '  Relation Managers',
            '<fg=gray>' . count($entries) . ' relation manager(s), ' . $totalPermissions . ' permission(s)</>'
        );
    }

    protected function syncPages(Panel $panel, string $guard): void
    {
        $pages = $this->getPages($panel);

        if ($pages === []) {
            return;
        }

        $prefix = $this->getPagePrefix();

        foreach ($pages as $pageClass) {
            $subject = $this->getPageSubject($pageClass);
            $key = $this->buildPagePermissionKey($prefix, $subject);
            $this->syncPermission($key, $guard);
        }

        $this->components->twoColumnDetail(
            '  Pages',
            '<fg=gray>' . count($pages) . ' page(s)</>'
        );
    }

    protected function syncWidgets(Panel $panel, string $guard): void
    {
        $widgets = $this->getWidgets($panel);

        if ($widgets === []) {
            return;
        }

        $prefix = $this->getWidgetPrefix();

        foreach ($widgets as $widgetClass) {
            $subject = $this->getWidgetSubject($widgetClass);
            $key = $this->buildWidgetPermissionKey($prefix, $subject);
            $this->syncPermission($key, $guard);
        }

        $this->components->twoColumnDetail(
            '  Widgets',
            '<fg=gray>' . count($widgets) . ' widget(s)</>'
        );
    }

    /**
     * @param  array<Panel>  $panels
     */
    protected function syncCustomPermissions(array $panels): void
    {
        $customKeys = $this->buildCustomPermissionKeys();

        if ($customKeys === []) {
            return;
        }

        $this->components->twoColumnDetail(
            '<fg=bright-blue>Custom Permissions</>',
            '<fg=gray>' . count($customKeys) . ' permission(s)</>'
        );

        $guards = collect($panels)
            ->map(fn (Panel $panel): string => $panel->getAuthGuard())
            ->unique()
            ->values()
            ->all();

        foreach ($customKeys as $key) {
            foreach ($guards as $guard) {
                $this->syncPermission($key, $guard, "{$key} ({$guard})");
            }
        }

        $this->newLine();
    }

    /**
     * Create a permission, record it as produced by this run, and report it.
     */
    protected function syncPermission(string $key, string $guard, ?string $label = null): void
    {
        $result = $this->createPermission($key, $guard);

        $this->recordStat($guard, $result['created']);
        $this->markExpected($guard, $key);

        if ($this->output->isVerbose()) {
            $status = $result['created'] ? '<fg=green>Created</>' : '<fg=gray>Exists</>';
            $this->components->twoColumnDetail('  ' . ($label ?? $key), $status);
        }
    }

    protected function markExpected(string $guard, string $key): void
    {
        $this->expected[$guard][$key] = true;
    }

    protected function validateGuard(string $guard): void
    {
        /** @var array<string, mixed>|null $guards */
        $guards = config('auth.guards');

        if ($guards === null || ! array_key_exists($guard, $guards)) {
            $this->components->warn(
                "Guard '{$guard}' is not configured in config/auth.php. You may encounter errors."
            );
        }
    }

    protected function recordStat(string $guard, bool $created): void
    {
        if ($created) {
            $this->stats[$guard]['created']++;
        } else {
            $this->stats[$guard]['existing']++;
        }
    }

    /**
     * Delete permissions for the synced guards that this run did not produce.
     *
     * @param  array<Panel>  $panels
     */
    protected function prunePermissions(array $panels): void
    {
        $this->components->info('Pruning permissions...');

        $syncedPanelIds = array_map(fn (Panel $panel): string => $panel->getId(), $panels);

        $guards = collect($panels)
            ->map(fn (Panel $panel): string => $panel->getAuthGuard())
            ->unique()
            ->values()
            ->all();

        $pruned = false;

        foreach ($guards as $guard) {
            if ($this->pruneGuard($guard, $syncedPanelIds)) {
                $pruned = true;
            }
        }

        if ($pruned) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        $this->newLine();
    }

    /**
     * Delete the orphaned permissions for a single guard. Returns true if anything was deleted.
     *
     * @param  array<string>  $syncedPanelIds
     */
    protected function pruneGuard(string $guard, array $syncedPanelIds): bool
    {
        $unsyncedPanels = $this->panelsSharingGuard($guard, $syncedPanelIds);

        if ($unsyncedPanels !== []) {
            $this->components->warn(
                "Skipping prune for guard '{$guard}': panel(s) [" . implode(', ', $unsyncedPanels)
                . '] share this guard but were not synced.'
            );

            return false;
        }

        $expectedKeys = array_keys($this->expected[$guard] ?? []);

        if ($expectedKeys === []) {
            $this->components->warn(
                "Skipping prune for guard '{$guard}': this run produced no permissions, "
                . 'so every permission for the guard would be deleted.'
            );

            return false;
        }

        $permissionModel = $this->getPermissionModel();

        /** @var Collection<int, Permission> $orphans */
        $orphans = $permissionModel::query()
            ->whereRaw('guard_name = ?', [$guard])
            ->whereNotIn('name', $expectedKeys)
            ->get();

        if ($orphans->isEmpty()) {
            $this->components->twoColumnDetail(
                "<fg=bright-blue>Prune:</> {$guard}",
                '<fg=gray>nothing to prune</>'
            );

            return false;
        }

        $this->components->twoColumnDetail(
            "<fg=bright-blue>Prune:</> {$guard}",
            '<fg=red>' . $orphans->count() . ' permission(s) to prune</>'
        );

        foreach ($orphans as $orphan) {
            $this->detachPermission($orphan);
            $orphan->delete();
            $this->stats[$guard]['deleted']++;

            if ($this->output->isVerbose()) {
                $this->components->twoColumnDetail("  {$orphan->name}", '<fg=red>Deleted</>');
            }
        }

        return true;
    }

    /**
     * Detach a permission from every role and user before deleting it, so the
     * pivot rows are cleaned up even on databases that don't enforce the
     * foreign-key cascade Spatie's migration relies on (e.g. SQLite without
     * the pragma, or MyISAM tables).
     */
    protected function detachPermission(Permission $permission): void
    {
        $permission->roles()->detach();

        if ($permission instanceof SpatiePermission) {
            $permission->users()->detach();
        }
    }

    /**
     * Panel IDs that use the given guard but were not part of this sync.
     *
     * @param  array<string>  $syncedPanelIds
     * @return array<int, string>
     */
    protected function panelsSharingGuard(string $guard, array $syncedPanelIds): array
    {
        return collect(Filament::getPanels())
            ->filter(fn (Panel $panel): bool => $panel->getAuthGuard() === $guard)
            ->map(fn (Panel $panel): string => $panel->getId())
            ->reject(fn (string $id): bool => in_array($id, $syncedPanelIds, true))
            ->values()
            ->all();
    }

    protected function displaySummary(): void
    {
        $this->components->info('Summary');

        $pruned = (bool) $this->option('prune');

        $totalCreated = 0;
        $totalExisting = 0;
        $totalDeleted = 0;

        foreach ($this->stats as $guard => $counts) {
            $totalCreated += $counts['created'];
            $totalExisting += $counts['existing'];
            $totalDeleted += $counts['deleted'];

            $this->components->twoColumnDetail(
                "Guard: {$guard}",
                $this->formatSummaryCounts($counts['created'], $counts['existing'], $counts['deleted'], $pruned)
            );
        }

        $this->newLine();
        $this->components->twoColumnDetail(
            '<fg=bright-white>Total</>',
            $this->formatSummaryCounts($totalCreated, $totalExisting, $totalDeleted, $pruned)
        );
    }

    protected function formatSummaryCounts(int $created, int $existing, int $deleted, bool $pruned): string
    {
        $summary = "<fg=green>{$created} created</>, <fg=gray>{$existing} existing</>";

        if ($pruned) {
            $summary .= ", <fg=red>{$deleted} deleted</>";
        }

        return $summary;
    }
}
