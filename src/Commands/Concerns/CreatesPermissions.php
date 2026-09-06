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
     * A sync calls createPermission() once per discovered key, and asking the
     * database "does this one exist?" every time is one query per permission. The
     * command already knows it owns the permission table for the guards it syncs,
     * so it reads the guard's names once and keeps the answer.
     *
     * Scoped to a single run: Artisan reuses command instances within a process, so
     * a second Artisan::call() would otherwise trust names read before the first
     * one -- and silently skip re-creating anything deleted in between.
     *
     * @var array<string, array<string, true>>
     */
    private array $existingPermissionNames = [];

    /**
     * Forget the permission names read during a previous run of this command.
     */
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

        // query()->create() rather than the model's static create(): the static one
        // re-reads Spatie's permission cache to raise PermissionAlreadyExists, and
        // that cache was just invalidated by the previous insert -- so in a loop it
        // reloads the whole table per permission. This is the same call Spatie's own
        // findOrCreate() makes, and it still fires the model events their
        // cache-forget hook relies on.
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
