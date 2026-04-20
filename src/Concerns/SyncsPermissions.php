<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian\Concerns;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Spatie\Permission\Contracts\Role;

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

        /** @var array<string, mixed> */
        return Arr::only($data, ['name']);
    }

    protected function syncCapturedPermissions(): void
    {
        if ($this->record instanceof Role) {
            $this->record->syncPermissions($this->permissionsToSync->all());
        }
    }
}
