<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian\Commands;

use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Auth\SessionGuard;
use Illuminate\Console\Command;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Traits\HasRoles;
use Symfony\Component\Console\Attribute\AsCommand;
use Waguilar\FilamentGuardian\Facades\Guardian;

#[AsCommand(name: 'guardian:super-admin', description: 'Create super-admin role and optionally assign to a user')]
class SetupSuperAdminCommand extends Command
{
    /** @var string */
    public $signature = 'guardian:super-admin
        {--panel= : Panel ID (required for non-tenant panels)}
        {--email= : User email to assign super-admin role}';

    /** @var string */
    public $description = 'Create super-admin role and optionally assign to a user';

    public function handle(): int
    {
        if (! Guardian::isSuperAdminEnabled()) {
            $this->components->error('Super-admin is not enabled in config/filament-guardian.php');

            return self::FAILURE;
        }

        /** @var string|null $panelId */
        $panelId = $this->option('panel');

        if ($panelId === null) {
            $this->components->error('The --panel option is required.');
            $this->newLine();
            $this->listAvailablePanels();

            return self::FAILURE;
        }

        $panels = Filament::getPanels();
        if (! isset($panels[$panelId])) {
            $this->components->error("Panel '{$panelId}' not found.");
            $this->newLine();
            $this->listAvailablePanels();

            return self::FAILURE;
        }

        $panel = $panels[$panelId];

        if ($panel->hasTenancy()) {
            $this->components->error("Panel '{$panelId}' has tenancy enabled.");
            $this->components->warn('Super-admin roles are automatically created when tenants are created.');
            $this->components->info('No manual setup is needed for tenant panels.');

            return self::FAILURE;
        }

        $role = Guardian::createSuperAdminRole($panelId);
        $this->components->info("Super-admin role created/verified for panel '{$panelId}'");
        $this->components->twoColumnDetail('Role', $role->name);
        $this->components->twoColumnDetail('Guard', $role->guard_name);

        /** @var string|null $email */
        $email = $this->option('email');

        if ($email !== null) {
            $this->assignRoleToUser($email, $panelId, $panel);
        }

        return self::SUCCESS;
    }

    protected function assignRoleToUser(string $email, string $panelId, Panel $panel): void
    {
        $guardName = $panel->getAuthGuard();

        /** @var SessionGuard $guard */
        $guard = Auth::guard($guardName);
        $provider = $guard->getProvider();

        if (! $provider instanceof EloquentUserProvider) {
            $this->components->error("Auth guard '{$guardName}' does not use an Eloquent user provider.");

            return;
        }

        /** @var class-string<Model> $userModel */
        $userModel = $provider->getModel();

        /** @var Model|null $user */
        $user = $userModel::query()->whereRaw('email = ?', [$email])->first();

        if ($user === null) {
            $this->components->error("User with email '{$email}' not found.");

            return;
        }

        $usedTraits = class_uses_recursive($user);
        if (! in_array(HasRoles::class, $usedTraits, true)) {
            $this->components->error('User model does not use the HasRoles trait.');

            return;
        }

        /** @var Authenticatable $authenticatable */
        $authenticatable = $user;

        Guardian::assignSuperAdminTo($authenticatable, $panelId);

        $this->newLine();
        $this->components->info("Super-admin role assigned to '{$email}'");
    }

    protected function listAvailablePanels(): void
    {
        $panels = Filament::getPanels();

        if ($panels === []) {
            $this->components->warn('No Filament panels found.');

            return;
        }

        $this->components->info('Available panels:');

        foreach ($panels as $panel) {
            $tenancy = $panel->hasTenancy() ? '<fg=yellow>(has tenancy - not supported)</>' : '<fg=green>(no tenancy)</>';
            $this->components->twoColumnDetail($panel->getId(), $tenancy);
        }
    }
}
