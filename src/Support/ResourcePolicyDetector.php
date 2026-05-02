<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian\Support;

use Illuminate\Support\Str;
use Waguilar\FilamentGuardian\Concerns\HasResourcePolicy;

final class ResourcePolicyDetector
{
    public static function usesResourcePolicy(string $resourceClass): bool
    {
        return in_array(
            HasResourcePolicy::class,
            class_uses_recursive($resourceClass),
            true,
        );
    }

    public static function getPolicyClass(string $resourceClass): string
    {
        return self::getPolicyNamespace() . '\\' . self::getPolicyClassBasename($resourceClass);
    }

    public static function getPolicyClassBasename(string $resourceClass): string
    {
        return self::getResourceSubject($resourceClass) . 'Policy';
    }

    public static function getResourceSubject(string $resourceClass): string
    {
        $basename = class_basename($resourceClass);

        return Str::endsWith($basename, 'Resource')
            ? Str::beforeLast($basename, 'Resource')
            : $basename;
    }

    public static function getPolicyFilePath(string $resourceClass): string
    {
        /** @var string $basePath */
        $basePath = config('filament-guardian.policies.path', app_path('Policies'));

        return $basePath . DIRECTORY_SEPARATOR . self::getPolicyClassBasename($resourceClass) . '.php';
    }

    private static function getPolicyNamespace(): string
    {
        /** @var string $basePath */
        $basePath = config('filament-guardian.policies.path', app_path('Policies'));

        $appPath = app_path();

        if (str_starts_with($basePath, $appPath)) {
            $relativePath = mb_substr($basePath, mb_strlen($appPath));
            $relativePath = ltrim($relativePath, DIRECTORY_SEPARATOR);

            return 'App\\' . str_replace(DIRECTORY_SEPARATOR, '\\', $relativePath);
        }

        $relativePath = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $basePath);

        return str_replace(DIRECTORY_SEPARATOR, '\\', $relativePath);
    }
}
