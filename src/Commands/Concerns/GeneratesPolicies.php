<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian\Commands\Concerns;

use Filament\Resources\Resource;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Str;
use RuntimeException;
use Waguilar\FilamentGuardian\Contracts\PermissionKeyBuilder;

trait GeneratesPolicies
{
    use ReadsResourceConfig;

    /** @var array<string, string> */
    protected array $stubCache = [];

    protected function generatePolicy(string $resourceClass): ?string
    {
        if (! is_subclass_of($resourceClass, Resource::class)) {
            return null;
        }

        $modelClass = $resourceClass::getModel();
        $policyInfo = $this->getPolicyInfo($modelClass);

        $stubVariables = $this->buildStubVariables($resourceClass, $modelClass, $policyInfo);
        $stub = $this->getStubForModel($modelClass);
        $content = $this->replaceStubVariables($stub, $stubVariables);

        $directory = dirname($policyInfo['path']);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $result = file_put_contents($policyInfo['path'], $content);

        if ($result === false) {
            throw new RuntimeException("Failed to write policy file: {$policyInfo['path']}");
        }

        return $policyInfo['path'];
    }

    protected function getPolicyPath(string $modelClass): string
    {
        return $this->getPolicyInfo($modelClass)['path'];
    }

    /**
     * Get policy path, namespace, and model name.
     *
     * Following Laravel's convention, all policies are placed directly
     * in the configured policies directory (flat structure).
     *
     * @return array{path: string, namespace: string, modelName: string}
     */
    protected function getPolicyInfo(string $modelClass): array
    {
        /** @var string $basePath */
        $basePath = config('filament-guardian.policies.path', app_path('Policies'));

        if (! class_exists($modelClass)) {
            throw new RuntimeException("Model class not found: {$modelClass}");
        }

        $modelName = class_basename($modelClass);

        return [
            'path' => $basePath . DIRECTORY_SEPARATOR . $modelName . 'Policy.php',
            'namespace' => $this->pathToNamespace($basePath),
            'modelName' => $modelName,
        ];
    }

    protected function pathToNamespace(string $path): string
    {
        $appPath = app_path();

        if (str_starts_with($path, $appPath)) {
            $relativePath = mb_substr($path, mb_strlen($appPath));
            $relativePath = mb_ltrim($relativePath, DIRECTORY_SEPARATOR);

            return 'App\\' . str_replace(DIRECTORY_SEPARATOR, '\\', $relativePath);
        }

        // Fallback: convert full relative path
        $relativePath = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $path);

        return str_replace(DIRECTORY_SEPARATOR, '\\', $relativePath);
    }

    /**
     * @param  array{path: string, namespace: string, modelName: string}  $policyInfo
     * @return array{namespace: string, authModelFqcn: string, authModelName: string, authModelVariable: string, modelFqcn: string, modelName: string, modelVariable: string, modelPolicy: string, methods: string}
     */
    protected function buildStubVariables(string $resourceClass, string $modelClass, array $policyInfo): array
    {
        $modelName = $policyInfo['modelName'];
        $modelVariable = Str::camel($modelName);
        $authModelInfo = $this->getAuthModelInfo();
        $permissionBuilder = app(PermissionKeyBuilder::class);

        $methodsContent = $this->generateMethodsContent(
            methods: $this->getResourceMethods($resourceClass),
            modelClass: $modelClass,
            modelName: $modelName,
            modelVariable: $modelVariable,
            authModelName: $authModelInfo['name'],
            authModelVariable: $authModelInfo['variable'],
            permissionBuilder: $permissionBuilder,
            subject: $this->getPermissionSubject($resourceClass, $modelClass),
            resourceClass: $resourceClass,
        );

        return [
            'namespace' => $policyInfo['namespace'],
            'authModelFqcn' => $authModelInfo['fqcn'],
            'authModelName' => $authModelInfo['name'],
            'authModelVariable' => $authModelInfo['variable'],
            'modelFqcn' => $modelClass,
            'modelName' => $modelName,
            'modelVariable' => $modelVariable,
            'modelPolicy' => $modelName . 'Policy',
            'methods' => $methodsContent,
        ];
    }

    /**
     * @param  array<int, string>  $methods
     */
    protected function generateMethodsContent(
        array $methods,
        string $modelClass,
        string $modelName,
        string $modelVariable,
        string $authModelName,
        string $authModelVariable,
        PermissionKeyBuilder $permissionBuilder,
        string $subject,
        string $resourceClass,
    ): string {
        /** @var array<string> $singleParamMethods */
        $singleParamMethods = config('filament-guardian.policies.single_parameter_methods', []);
        $isAuthenticatable = is_subclass_of($modelClass, Authenticatable::class);

        $methodsContent = '';

        foreach ($methods as $method) {
            $isSingleParam = in_array($method, $singleParamMethods, true) || $isAuthenticatable;
            $stubName = $isSingleParam ? 'SingleParamMethod' : 'MultiParamMethod';

            $methodsContent .= strtr($this->getStub($stubName), [
                '{{ methodName }}' => $method,
                '{{ authModelName }}' => $authModelName,
                '{{ authModelVariable }}' => $authModelVariable,
                '{{ modelName }}' => $modelName,
                '{{ modelVariable }}' => $modelVariable,
                '{{ permission }}' => $permissionBuilder->build($method, $subject, $resourceClass),
            ]);
        }

        return $methodsContent;
    }

    protected function getPermissionSubject(string $resourceClass, string $modelClass): string
    {
        $resourceConfig = $this->getManagedResourceConfig($resourceClass);

        if (isset($resourceConfig['subject']) && is_string($resourceConfig['subject'])) {
            return $resourceConfig['subject'];
        }

        /** @var string $subjectType */
        $subjectType = config('filament-guardian.resources.subject', 'model');

        return $subjectType === 'class'
            ? class_basename($resourceClass)
            : class_basename($modelClass);
    }

    /**
     * Get auth model information for policy generation.
     *
     * Uses the generic Laravel AuthUser base class for portability.
     *
     * @return array{fqcn: string, name: string, variable: string}
     */
    protected function getAuthModelInfo(): array
    {
        return [
            'fqcn' => 'Illuminate\\Foundation\\Auth\\User as AuthUser',
            'name' => 'AuthUser',
            'variable' => 'authUser',
        ];
    }

    protected function getStubForModel(string $modelClass): string
    {
        $stubName = is_subclass_of($modelClass, Authenticatable::class)
            ? 'AuthenticatablePolicy'
            : 'DefaultPolicy';

        return $this->getStub($stubName);
    }

    protected function getStub(string $name): string
    {
        if (isset($this->stubCache[$name])) {
            return $this->stubCache[$name];
        }

        $customPath = base_path("stubs/filament-guardian/{$name}.stub");

        if (file_exists($customPath)) {
            return $this->stubCache[$name] = (string) file_get_contents($customPath);
        }

        $packagePath = dirname(__DIR__, 3) . "/stubs/{$name}.stub";

        if (file_exists($packagePath)) {
            return $this->stubCache[$name] = (string) file_get_contents($packagePath);
        }

        throw new RuntimeException("Stub file not found: {$name}.stub");
    }

    /**
     * @param  array<string, string>  $variables
     */
    protected function replaceStubVariables(string $stub, array $variables): string
    {
        $replacements = [];
        foreach ($variables as $key => $value) {
            $replacements["{{ {$key} }}"] = $value;
        }

        return strtr($stub, $replacements);
    }
}
