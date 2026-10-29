<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian\Concerns;

use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Waguilar\FilamentGuardian\Support\PolicyAuthorizer;
use Waguilar\FilamentGuardian\Support\RelationManagerPolicyRegistrar;

trait HasRelationManagerPolicy
{
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        if (static::shouldSkipAuthorization()) {
            return true;
        }

        RelationManagerPolicyRegistrar::ensureRegistered(static::class);

        return PolicyAuthorizer::inspect(
            subject: static::class,
            action: 'viewAny',
            methodName: 'viewAny',
            arguments: [static::class],
            checkPolicyExistence: static::shouldCheckPolicyExistence(),
        )->allowed();
    }

    public function getAuthorizationResponse(string $action, ?Model $record = null): Response
    {
        if (static::shouldSkipAuthorization()) {
            return Response::allow();
        }

        RelationManagerPolicyRegistrar::ensureRegistered(static::class);

        $arguments = $record !== null ? [static::class, $record] : [static::class];

        return PolicyAuthorizer::inspect(
            subject: static::class,
            action: $action,
            methodName: $action,
            arguments: $arguments,
            checkPolicyExistence: static::shouldCheckPolicyExistence(),
        );
    }
}
