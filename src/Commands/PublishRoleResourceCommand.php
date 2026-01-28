<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian\Commands;

use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;

use function Laravel\Prompts\select;

class PublishRoleResourceCommand extends Command
{
    public $signature = 'filament-guardian:publish-role-resource {panel?}';

    public $description = 'Publish the RoleResource to a Filament panel for customization';

    public function __construct(
        protected Filesystem $filesystem,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $panelId = $this->argument('panel') ?? $this->askForPanel();

        if (! is_string($panelId)) {
            $this->error('No panel selected.');

            return self::FAILURE;
        }

        $panel = Filament::getPanel($panelId);

        $directories = $panel->getResourceDirectories();
        $namespaces = $panel->getResourceNamespaces();

        if (empty($directories) || empty($namespaces)) {
            $this->error("Panel '{$panelId}' has no resource directories configured.");
            $this->error('Make sure your panel uses discoverResources().');

            return self::FAILURE;
        }

        /** @var string $targetDirectory */
        $targetDirectory = Arr::first($directories);
        /** @var string $targetNamespace */
        $targetNamespace = Arr::first($namespaces);

        $destinationDirectory = $targetDirectory . '/Roles';

        if ($this->filesystem->isDirectory($destinationDirectory)) {
            $this->error("RoleResource already exists at: {$destinationDirectory}");
            $this->error('Remove it first if you want to republish.');

            return self::FAILURE;
        }

        $this->publishStubs($destinationDirectory, $targetNamespace . '\\Roles');

        $this->info("RoleResource published to: {$destinationDirectory}");
        $this->info("Namespace: {$targetNamespace}\\Roles");

        return self::SUCCESS;
    }

    protected function askForPanel(): ?string
    {
        /** @var array<string, string> $panels */
        $panels = collect(Filament::getPanels())
            ->mapWithKeys(fn (Panel $panel) => [$panel->getId() => $panel->getId()])
            ->toArray();

        if ($panels === []) {
            return null;
        }

        if (count($panels) === 1) {
            return array_key_first($panels);
        }

        /** @var string $selected */
        $selected = select(
            label: 'Which panel would you like to publish the RoleResource to?',
            options: $panels,
        );

        return $selected;
    }

    protected function publishStubs(string $destination, string $namespace): void
    {
        $stubsDirectory = __DIR__ . '/../../stubs/Roles';

        $this->filesystem->ensureDirectoryExists($destination);
        $this->filesystem->ensureDirectoryExists($destination . '/Pages');
        $this->filesystem->ensureDirectoryExists($destination . '/RelationManagers');
        $this->filesystem->ensureDirectoryExists($destination . '/Schemas');
        $this->filesystem->ensureDirectoryExists($destination . '/Tables');

        $stubs = [
            'RoleResource.stub' => 'RoleResource.php',
            'Pages/ListRoles.stub' => 'Pages/ListRoles.php',
            'Pages/CreateRole.stub' => 'Pages/CreateRole.php',
            'Pages/EditRole.stub' => 'Pages/EditRole.php',
            'Pages/ViewRole.stub' => 'Pages/ViewRole.php',
            'RelationManagers/UsersRelationManager.stub' => 'RelationManagers/UsersRelationManager.php',
            'Schemas/RoleForm.stub' => 'Schemas/RoleForm.php',
            'Schemas/RoleInfolist.stub' => 'Schemas/RoleInfolist.php',
            'Tables/RolesTable.stub' => 'Tables/RolesTable.php',
        ];

        foreach ($stubs as $stub => $output) {
            $contents = $this->filesystem->get($stubsDirectory . '/' . $stub);

            $contents = str_replace('{{ namespace }}', $namespace, $contents);

            $this->filesystem->put($destination . '/' . $output, $contents);
        }
    }
}
