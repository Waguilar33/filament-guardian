<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian\Support;

use Illuminate\Support\Facades\Gate;

final class RelationManagerPolicyRegistrar
{
    public static function ensureRegistered(string $relationManagerClass): void
    {
        if (array_key_exists($relationManagerClass, Gate::policies())) {
            return;
        }

        $policyClass = RelationManagerPolicyDetector::getPolicyClass($relationManagerClass);

        if (! class_exists($policyClass)) {
            return;
        }

        Gate::policy($relationManagerClass, $policyClass);
    }
}
