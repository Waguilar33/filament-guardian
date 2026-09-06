<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian\Commands;

use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Resource;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Waguilar\FilamentGuardian\Commands\Concerns\DiscoversEntities;
use Waguilar\FilamentGuardian\Commands\Concerns\GeneratesPolicies;

use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;

#[AsCommand(name: 'guardian:policies', description: 'Generate policies for Filament resources and relation managers')]
class GeneratePoliciesCommand extends Command
{
    use DiscoversEntities;
    use GeneratesPolicies;

    /** @var string */
    public $signature = 'guardian:policies
        {--panel= : The panel ID to generate policies for}
        {--all-panels : Generate policies for all panels}
        {--resource= : Generate policy for a specific resource (comma-separated for multiple)}
        {--all-resources : Generate policies for all resources in the panel}
        {--no-relation-managers : Skip auto-discovered relation managers}
        {--all-relation-managers : Generate policies for all eligible relation managers}
        {--force : Overwrite existing policies}';

    /** @var string */
    public $description = 'Generate policies for Filament resources and relation managers';

    protected int $totalGenerated = 0;

    protected int $totalSkipped = 0;

    public function handle(): int
    {
        // processPanel() switches the current panel. Restore whatever was current
        // on the way out so the rest of the process is not left with an arbitrary
        // panel silently current.
        $previousPanel = Filament::getCurrentPanel();

        try {
            if ($this->option('all-panels')) {
                return $this->handleAllPanels();
            }

            return $this->handleSinglePanel();
        } finally {
            Filament::setCurrentPanel($previousPanel);
        }
    }

    protected function handleAllPanels(): int
    {
        $panels = Filament::getPanels();

        if ($panels === []) {
            $this->components->warn('No panels found.');

            return self::SUCCESS;
        }

        $this->components->info('Generating policies for ' . count($panels) . ' panel(s)...');
        $this->newLine();

        foreach ($panels as $panel) {
            $this->processPanel($panel);
        }

        $this->newLine();
        $this->components->info("Total: Generated {$this->totalGenerated} policies, skipped {$this->totalSkipped}.");

        return self::SUCCESS;
    }

    protected function handleSinglePanel(): int
    {
        $panelId = $this->option('panel');

        if (! is_string($panelId)) {
            /** @var array<int, string> $panels */
            $panels = collect(Filament::getPanels())->keys()->values()->all();

            if (count($panels) === 1) {
                $panelId = $panels[0];
            } else {
                $panelId = select(
                    label: 'Which panel do you want to generate policies for?',
                    options: $panels,
                );
            }
        }

        $availablePanels = collect(Filament::getPanels())->keys()->all();
        if (! in_array($panelId, $availablePanels, true)) {
            $this->components->error("Panel '{$panelId}' not found.");

            return self::FAILURE;
        }

        $panel = Filament::getPanel($panelId);

        return $this->processPanel($panel) ? self::SUCCESS : self::FAILURE;
    }

    protected function processPanel(Panel $panel): bool
    {
        Filament::setCurrentPanel($panel);

        $this->components->twoColumnDetail(
            "<fg=bright-blue>Panel:</> {$panel->getId()}",
            ''
        );

        $resources = $this->getResourcesToProcess($panel);
        $relationManagers = $this->getRelationManagersToProcess($panel);

        if ($resources === [] && $relationManagers === []) {
            $this->components->warn('  No resources or relation managers found to generate policies for.');

            return true;
        }

        $generated = 0;
        $skipped = 0;

        foreach ($resources as $resourceClass) {
            if ($this->isExcluded($resourceClass)) {
                $this->components->twoColumnDetail("  {$resourceClass}", '<fg=yellow>Excluded</>');
                $skipped++;

                continue;
            }

            $policyPath = $this->getPolicyPath($resourceClass);

            if (file_exists($policyPath) && ! $this->option('force')) {
                $this->components->twoColumnDetail("  {$resourceClass}", '<fg=yellow>Exists (use --force to overwrite)</>');
                $skipped++;

                continue;
            }

            $path = $this->generatePolicy($resourceClass);

            if ($path !== null) {
                $relativePath = str_replace(base_path() . '/', '', $path);
                $this->components->twoColumnDetail("  {$resourceClass}", "<fg=green>{$relativePath}</>");
                $generated++;
            }
        }

        foreach ($relationManagers as $entry) {
            $rmClass = $entry['class'];
            $modelClass = $entry['related_model'];

            $policyPath = $this->getRelationManagerPolicyPath($rmClass, $modelClass);

            if (file_exists($policyPath) && ! $this->option('force')) {
                $this->components->twoColumnDetail("  {$rmClass}", '<fg=yellow>Exists (use --force to overwrite)</>');
                $skipped++;

                continue;
            }

            $path = $this->generatePolicyForRelationManager($rmClass, $modelClass);

            if ($path !== null) {
                $relativePath = str_replace(base_path() . '/', '', $path);
                $this->components->twoColumnDetail("  {$rmClass}", "<fg=green>{$relativePath}</>");
                $generated++;
            }
        }

        $this->totalGenerated += $generated;
        $this->totalSkipped += $skipped;

        $this->components->twoColumnDetail(
            '  Summary',
            "<fg=green>{$generated} generated</>, <fg=gray>{$skipped} skipped</>"
        );

        return true;
    }

    /**
     * @return array<int, class-string<resource>>
     */
    protected function getResourcesToProcess(Panel $panel): array
    {
        /** @var array<int, class-string<resource>> $resources */
        $resources = array_values($panel->getResources());

        // When using --all-panels, automatically include all resources
        if ($this->option('all-resources') || $this->option('all-panels')) {
            return $resources;
        }

        $specificResources = $this->option('resource');

        if (is_string($specificResources)) {
            return $this->filterResourcesByName($resources, $specificResources);
        }

        return $this->promptForResources($resources);
    }

    /**
     * @param  array<int, class-string<resource>>  $resources
     * @return array<int, class-string<resource>>
     */
    protected function filterResourcesByName(array $resources, string $specificResources): array
    {
        $requestedNames = array_map('trim', explode(',', $specificResources));

        /** @var array<int, class-string<resource>> $filtered */
        $filtered = collect($resources)
            ->filter(function (string $resource) use ($requestedNames): bool {
                $className = class_basename($resource);

                return in_array($className, $requestedNames, true)
                    || in_array(str_replace('Resource', '', $className), $requestedNames, true);
            })
            ->values()
            ->all();

        return $filtered;
    }

    /**
     * @param  array<int, class-string<resource>>  $resources
     * @return array<int, class-string<resource>>
     */
    protected function promptForResources(array $resources): array
    {
        /** @var array<string, string> $options */
        $options = collect($resources)
            ->mapWithKeys(fn (string $resource): array => [$resource => class_basename($resource)])
            ->all();

        /** @var array<int, class-string<resource>> $selected */
        $selected = multiselect(
            label: 'Which resources do you want to generate policies for?',
            options: $options,
            required: true,
            hint: 'Use --all-resources to skip this prompt',
        );

        return $selected;
    }

    protected function isExcluded(string $resourceClass): bool
    {
        /** @var array<class-string> $excluded */
        $excluded = config('filament-guardian.resources.exclude', []);

        return in_array($resourceClass, $excluded, true);
    }

    /**
     * @return array<class-string<RelationManager>, array{class: class-string<RelationManager>, related_model: class-string, parent_resource: class-string<resource>}>
     */
    protected function getRelationManagersToProcess(Panel $panel): array
    {
        if ($this->option('no-relation-managers')) {
            return [];
        }

        $entries = $this->getRelationManagers($panel);

        if ($entries === []) {
            return [];
        }

        if ($this->option('all-relation-managers') || $this->option('all-resources') || $this->option('all-panels')) {
            return $entries;
        }

        return $this->promptForRelationManagers($entries);
    }

    /**
     * @param  array<class-string<RelationManager>, array{class: class-string<RelationManager>, related_model: class-string, parent_resource: class-string<resource>}>  $entries
     * @return array<class-string<RelationManager>, array{class: class-string<RelationManager>, related_model: class-string, parent_resource: class-string<resource>}>
     */
    protected function promptForRelationManagers(array $entries): array
    {
        if ($entries === []) {
            return [];
        }

        $options = [];

        foreach ($entries as $rmClass => $entry) {
            $options[$rmClass] = class_basename($rmClass) . ' → ' . class_basename($entry['related_model']);
        }

        /** @var array<int, class-string<RelationManager>> $selected */
        $selected = multiselect(
            label: 'Which relation managers do you want to generate policies for?',
            options: $options,
            hint: 'Use --all-relation-managers to skip this prompt, or --no-relation-managers to skip relation managers entirely',
        );

        return array_intersect_key($entries, array_flip($selected));
    }
}
