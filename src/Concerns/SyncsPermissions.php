<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian\Concerns;

use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Spatie\Permission\Contracts\Role;
use Waguilar\FilamentGuardian\Facades\Guardian;

trait SyncsPermissions
{
    use ExtractsPermissions;

    /** @var Collection<int, string> */
    protected Collection $permissionsToSync;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function capturePermissionsFromFormData(array $data): array
    {
        $this->permissionsToSync = $this->extractPermissions($data);

        $this->haltWhenGrantingUnheldPermissions();

        /** @var array<string, mixed> */
        return Arr::only($data, ['name']);
    }

    protected function haltWhenGrantingUnheldPermissions(): void
    {
        /** @var Collection<int, string> $currentPermissions */
        $currentPermissions = $this->record instanceof Role
            ? collect($this->record->permissions->pluck('name'))
            : collect();

        $unheld = Guardian::permissionsUserCannotGrant(
            Filament::auth()->user(),
            $this->permissionsToSync->diff($currentPermissions),
        );

        if ($unheld->isEmpty()) {
            return;
        }

        Notification::make()
            ->danger()
            ->title(__('filament-guardian::filament-guardian.privilege_escalation.cannot_grant'))
            ->body(__('filament-guardian::filament-guardian.privilege_escalation.missing', ['permissions' => $unheld->implode(', ')]))
            ->send();

        $this->halt();
    }

    protected function syncCapturedPermissions(): void
    {
        if ($this->record instanceof Role) {
            $this->record->syncPermissions($this->permissionsToSync->all());
        }
    }
}
