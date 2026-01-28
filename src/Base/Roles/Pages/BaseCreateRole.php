<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian\Base\Roles\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Spatie\Permission\Contracts\Role;

abstract class BaseCreateRole extends CreateRecord
{
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
     */
    protected function afterCreate(): void
    {
        $record = $this->record;

        if ($record instanceof Model && $record instanceof Role) {
            $record->syncPermissions($this->permissionsToSync->all());
        }
    }

    /**
     * Extract all permission field values from form data.
     *
     * @param  array<string, mixed>  $data
     * @return Collection<int, string>
     */
    protected function extractPermissions(array $data): Collection
    {
        $excludedKeys = [
            'name',
        ];

        /** @var Collection<int, string> $permissions */
        $permissions = collect($data)
            ->filter(fn (mixed $value, string $key): bool => ! in_array($key, $excludedKeys, true))
            ->filter(fn (mixed $value, string $key): bool => ! str_starts_with($key, 'select_all_'))
            ->filter(fn (mixed $value, string $key): bool => str_ends_with($key, '_permissions'))
            ->flatMap(fn (mixed $value): array => is_array($value) ? $value : [])
            ->filter(fn (mixed $value): bool => is_string($value))
            ->unique()
            ->values();

        return $permissions;
    }
}
