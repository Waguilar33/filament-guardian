<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian\Commands\Concerns;

use Spatie\Permission\Contracts\Permission;
use Spatie\Permission\PermissionRegistrar;
use Waguilar\FilamentGuardian\Contracts\PermissionKeyBuilder;

trait CreatesPermissions
{
    /**
     * Create a permission in the database if it doesn't exist.
     *
     * @return array{name: string, created: bool}
     */
    protected function createPermission(string $name, string $guard): array
    {
        $permissionModel = $this->getPermissionModel();

        $exists = $permissionModel::query()
            ->whereRaw('name = ?', [$name])
            ->whereRaw('guard_name = ?', [$guard])
            ->exists();

        if (! $exists) {
            $permissionModel::create([
                'name' => $name,
                'guard_name' => $guard,
            ]);
        }

        return [
            'name' => $name,
            'created' => ! $exists,
        ];
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
