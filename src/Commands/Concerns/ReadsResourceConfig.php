<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian\Commands\Concerns;

trait ReadsResourceConfig
{
    /**
     * Get the methods/actions for a resource.
     *
     * @return array<int, string>
     */
    protected function getResourceMethods(string $resourceClass): array
    {
        /** @var array<int, string> $defaultMethods */
        $defaultMethods = config('filament-guardian.policies.methods', []);

        $resourceConfig = $this->getManagedResourceConfig($resourceClass);

        if ($resourceConfig === null) {
            return array_values($defaultMethods);
        }

        /** @var array<int, string>|null $resourceMethods */
        $resourceMethods = $resourceConfig['methods'] ?? null;

        if ($resourceMethods === null) {
            return array_values($defaultMethods);
        }

        /** @var bool $merge */
        $merge = config('filament-guardian.policies.merge', true);

        if ($merge) {
            return array_values(array_unique(array_merge($defaultMethods, $resourceMethods)));
        }

        return array_values($resourceMethods);
    }

    /**
     * Get the managed resource configuration.
     *
     * @return array<string, mixed>|null
     */
    protected function getManagedResourceConfig(string $resourceClass): ?array
    {
        /** @var array<class-string, array<string, mixed>> $managedResources */
        $managedResources = config('filament-guardian.resources.manage', []);

        return $managedResources[$resourceClass] ?? null;
    }
}
