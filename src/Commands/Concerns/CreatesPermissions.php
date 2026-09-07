<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian\Commands\Concerns;

use Spatie\Permission\Contracts\Permission;
use Spatie\Permission\PermissionRegistrar;
use Waguilar\FilamentGuardian\Contracts\PermissionKeyBuilder;

trait CreatesPermissions
{
    /**
     * Existing permission names for the current run, keyed by guard then name.
     *
     * Must be cleared per run: Artisan reuses command instances within a process, so
     * a second Artisan::call() would trust names read before the first and silently
     * skip re-creating anything deleted in between.
     *
     * @var array<string, array<string, true>>
     */
    private array $existingPermissionNames = [];

    protected function forgetExistingPermissionNames(): void
    {
        $this->existingPermissionNames = [];
    }

    /**
     * Create a permission in the database if it doesn't exist.
     *
     * @return array{name: string, created: bool}
     */
    protected function createPermission(string $name, string $guard): array
    {
        $existing = $this->existingPermissionNamesForGuard($guard);

        if (isset($existing[$name])) {
            return [
                'name' => $name,
                'created' => false,
            ];
        }

        // Not the model's static create(): it re-reads Spatie's permission cache to
        // raise PermissionAlreadyExists, and the previous insert just invalidated that
        // cache, so in a loop it reloads the whole table per permission. Spatie's own
        // findOrCreate() calls query()->create() for the same reason.
        $this->getPermissionModel()::query()->create([
            'name' => $name,
            'guard_name' => $guard,
        ]);

        $this->existingPermissionNames[$guard][$name] = true;

        return [
            'name' => $name,
            'created' => true,
        ];
    }

    /**
     * The permission names already stored for a guard, read once per run.
     *
     * @return array<string, true>
     */
    private function existingPermissionNamesForGuard(string $guard): array
    {
        if (! array_key_exists($guard, $this->existingPermissionNames)) {
            /** @var array<int, string> $names */
            $names = $this->getPermissionModel()::query()
                ->whereRaw('guard_name = ?', [$guard])
                ->pluck('name')
                ->all();

            $this->existingPermissionNames[$guard] = array_fill_keys($names, true);
        }

        return $this->existingPermissionNames[$guard];
    }

    /**
     * Build permission keys for a resource.
     *
     * @param  array<int, string>  $methods
     * @return array<int, string>
     */
    protected function buildResourcePermissionKeys(string $subject, array $methods): array
    {
        $builder = $this->getPermissionKeyBuilder();

        return array_map(
            fn (string $method): string => $builder->build($method, $subject),
            $methods
        );
    }

    /**
     * Build permission key for a page.
     */
    protected function buildPagePermissionKey(string $prefix, string $subject): string
    {
        return $this->getPermissionKeyBuilder()->build($prefix, $subject);
    }

    /**
     * Build permission key for a widget.
     */
    protected function buildWidgetPermissionKey(string $prefix, string $subject): string
    {
        return $this->getPermissionKeyBuilder()->build($prefix, $subject);
    }

    /**
     * Get custom permission keys from config.
     *
     * @return array<int, string>
     */
    protected function buildCustomPermissionKeys(): array
    {
        /** @var array<string, string> $customPermissions */
        $customPermissions = config('filament-guardian.custom_permissions', []);

        return array_keys($customPermissions);
    }

    /**
     * Get the permission model class.
     *
     * @return class-string<Permission>
     */
    protected function getPermissionModel(): string
    {
        /** @var class-string<Permission> */
        return app(PermissionRegistrar::class)->getPermissionClass();
    }

    /**
     * Get the permission key builder instance.
     */
    protected function getPermissionKeyBuilder(): PermissionKeyBuilder
    {
        return app(PermissionKeyBuilder::class);
    }
}
