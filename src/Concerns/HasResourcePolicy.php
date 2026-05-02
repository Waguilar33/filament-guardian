<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian\Concerns;

use Filament\Facades\Filament;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use LogicException;

trait HasResourcePolicy
{
    public static function getAuthorizationResponse(string $action, ?Model $record = null): Response
    {
        if (static::shouldSkipAuthorization()) {
            return Response::allow();
        }

        $user = Filament::auth()->user();
        $policy = Gate::getPolicyFor(static::class);
        $arguments = $record !== null ? [static::class, $record] : [static::class];

        if ((is_object($policy) || is_string($policy)) && method_exists($policy, $action)) {
            return Gate::forUser($user)->inspect($action, $arguments);
        }

        if (static::shouldCheckPolicyExistence() && Filament::isAuthorizationStrict()) {
            $policyClass = match (true) {
                is_string($policy) => $policy,
                is_object($policy) => $policy::class,
                default => null,
            };

            $resourceClass = static::class;

            throw new LogicException(blank($policyClass)
                ? "Strict authorization mode is enabled, but no policy was found for [{$resourceClass}]. Run 'php artisan guardian:policies' to generate it."
                : "Strict authorization mode is enabled, but no [{$action}()] method was found on [{$policyClass}].");
        }

        return Gate::forUser($user)->inspect($action, $arguments);
    }
}
