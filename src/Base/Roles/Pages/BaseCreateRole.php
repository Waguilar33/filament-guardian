<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian\Base\Roles\Pages;

use Filament\Resources\Pages\CreateRecord;
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
        return $this->capturePermissionsFromFormData($data);
    }

    protected function afterCreate(): void
    {
        $this->syncCapturedPermissions();
    }
}
