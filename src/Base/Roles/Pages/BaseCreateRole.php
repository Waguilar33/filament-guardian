<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian\Base\Roles\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Spatie\Permission\Contracts\Role;
use Waguilar\FilamentGuardian\Concerns\ExtractsPermissions;

abstract class BaseCreateRole extends CreateRecord
{
    use ExtractsPermissions;

    /**
     * Permissions extracted from form data to sync after create.
     *
     * @var Collection<int, string>
     */
    protected Collection $permissionsToSync;

    /**
     * Extract permissions from form data before creating the role.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->permissionsToSync = $this->extractPermissions($data);

        /** @var array<string, mixed> $result */
        $result = Arr::only($data, ['name']);

        return $result;
    }

    /**
     * Sync permissions after the role is created.
     *
     * @override Filament lifecycle hook
     */
    protected function afterCreate(): void
    {
        $record = $this->record;

        if ($record instanceof Role) {
            $record->syncPermissions($this->permissionsToSync->all());
        }
    }
}
