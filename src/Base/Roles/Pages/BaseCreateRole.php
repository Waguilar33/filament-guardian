<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian\Base\Roles\Pages;

use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use RuntimeException;
use Waguilar\FilamentGuardian\Concerns\SyncsPermissions;

abstract class BaseCreateRole extends CreateRecord
{
    use SyncsPermissions;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $panel = Filament::getCurrentPanel() ?? throw new RuntimeException('No Filament panel is currently active.');

        $data = $this->capturePermissionsFromFormData($data);

        // Spatie's Role constructor defaults guard_name to auth.defaults.guard, so it
        // is never null and a creating() hook cannot fill it in. Without this, a role
        // made in a panel on any other guard is saved under the default one and is
        // immediately invisible to the panel that created it.
        $data['guard_name'] = $panel->getAuthGuard();

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->syncCapturedPermissions();
    }
}
