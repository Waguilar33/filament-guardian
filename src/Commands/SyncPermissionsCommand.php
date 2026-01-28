<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian\Commands;

use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Console\Command;
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
        {--panel=* : Specific panel IDs to sync (syncs all if not specified)}';

    /** @var string */
    public $description = 'Sync permissions for all Filament panels';

    /** @var array<string, array{created: int, existing: int}> */
    protected array $stats = [];

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

        $this->stats[$guard] ??= ['created' => 0, 'existing' => 0];

        $this->syncResources($panel, $guard);
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
                $result = $this->createPermission($key, $guard);
                $this->recordStat($guard, $result['created']);

                if ($this->output->isVerbose()) {
                    $status = $result['created'] ? '<fg=green>Created</>' : '<fg=gray>Exists</>';
                    $this->components->twoColumnDetail("  {$key}", $status);
                }
            }
        }

        $this->components->twoColumnDetail(
            '  Resources',
            '<fg=gray>' . count($resources) . ' resource(s), ' . count($resources) * count($this->getResourceMethods($resources[0] ?? '')) . ' permission(s)</>'
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
            $result = $this->createPermission($key, $guard);
            $this->recordStat($guard, $result['created']);

            if ($this->output->isVerbose()) {
                $status = $result['created'] ? '<fg=green>Created</>' : '<fg=gray>Exists</>';
                $this->components->twoColumnDetail("  {$key}", $status);
            }
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
            $result = $this->createPermission($key, $guard);
            $this->recordStat($guard, $result['created']);

            if ($this->output->isVerbose()) {
                $status = $result['created'] ? '<fg=green>Created</>' : '<fg=gray>Exists</>';
                $this->components->twoColumnDetail("  {$key}", $status);
            }
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

        // Get unique guards from all panels
        $guards = collect($panels)
            ->map(fn (Panel $panel): string => $panel->getAuthGuard())
            ->unique()
            ->values()
            ->all();

        foreach ($customKeys as $key) {
            foreach ($guards as $guard) {
                $result = $this->createPermission($key, $guard);
                $this->recordStat($guard, $result['created']);

                if ($this->output->isVerbose()) {
                    $status = $result['created'] ? '<fg=green>Created</>' : '<fg=gray>Exists</>';
                    $this->components->twoColumnDetail("  {$key} ({$guard})", $status);
                }
            }
        }

        $this->newLine();
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

    protected function displaySummary(): void
    {
        $this->components->info('Summary');

        $totalCreated = 0;
        $totalExisting = 0;

        foreach ($this->stats as $guard => $counts) {
            $totalCreated += $counts['created'];
            $totalExisting += $counts['existing'];

            $this->components->twoColumnDetail(
                "Guard: {$guard}",
                "<fg=green>{$counts['created']} created</>, <fg=gray>{$counts['existing']} existing</>"
            );
        }

        $this->newLine();
        $this->components->twoColumnDetail(
            '<fg=bright-white>Total</>',
            "<fg=green>{$totalCreated} created</>, <fg=gray>{$totalExisting} existing</>"
        );
    }
}
