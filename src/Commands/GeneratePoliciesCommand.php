<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian\Commands;

use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Resources\Resource;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Waguilar\FilamentGuardian\Commands\Concerns\GeneratesPolicies;

use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;

#[AsCommand(name: 'guardian:policies', description: 'Generate policies for Filament resources')]
class GeneratePoliciesCommand extends Command
{
    use GeneratesPolicies;

    /** @var string */
    public $signature = 'guardian:policies
        {--panel= : The panel ID to generate policies for}
        {--all-panels : Generate policies for all panels}
        {--resource= : Generate policy for a specific resource (comma-separated for multiple)}
        {--all-resources : Generate policies for all resources in the panel}
        {--force : Overwrite existing policies}';

    /** @var string */
    public $description = 'Generate policies for Filament resources';

    protected int $totalGenerated = 0;

    protected int $totalSkipped = 0;

    public function handle(): int
    {
        if ($this->option('all-panels')) {
            return $this->handleAllPanels();
        }

        return $this->handleSinglePanel();
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

        if ($resources === []) {
            $this->components->warn('  No resources found to generate policies for.');

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

            $policyPath = $this->getPolicyPath($resourceClass::getModel());

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
}
