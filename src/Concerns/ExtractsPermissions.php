<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian\Concerns;

use Illuminate\Support\Collection;

/**
 * Trait for extracting permission field values from form data.
 *
 * Used by role create/edit pages to gather all permission selections
 * from the form's various checkbox fields.
 */
trait ExtractsPermissions
{
    /**
     * Extract all permission field values from form data.
     *
     * Collects values from all fields ending in '_permissions' while excluding
     * non-permission fields like 'name' and 'select_all_*' toggles.
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
