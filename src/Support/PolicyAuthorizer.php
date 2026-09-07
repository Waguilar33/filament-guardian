<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian\Support;

use Filament\Facades\Filament;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;
use LogicException;
use UnitEnum;

/**
 * Resolves an authorization response for a resource or relation manager class.
 *
 * Mirrors Filament's get_authorization_response(), which keys policies on the
 * model; this keys them on the resource or relation manager, so one model reached
 * through two resources can be authorized differently.
 *
 * It differs from Filament's helper in one deliberate way: when no policy covers
 * the ability, Filament defaults to allowing it, while this denies. Filament treats
 * an unpoliced resource as public; a permission package cannot, or forgetting to
 * generate a policy would silently open the resource to everyone. Gate::before
 * still runs either way, so the super-admin bypass is unaffected.
 */
final class PolicyAuthorizer
{
    /**
     * @param  array<int, mixed>  $arguments
     */
    public static function inspect(
        string $subject,
        string | UnitEnum $action,
        string $methodName,
        array $arguments,
        bool $checkPolicyExistence,
    ): Response {
        $user = Filament::auth()->user();
        $policy = Gate::getPolicyFor($subject);
        $hasPolicyMethod = (is_object($policy) || is_string($policy)) && method_exists($policy, $methodName);

        if (! $checkPolicyExistence) {
            // The caller opted out of requiring a policy, so a Gate::define()d ability
            // is enough on its own. Strict mode still refuses to let both be missing.
            if (Filament::isAuthorizationStrict() && ! Gate::forUser($user)->has($action) && ! $hasPolicyMethod) {
                throw new LogicException(self::missingPolicyMessage($subject, $methodName, $policy, abilityChecked: true));
            }

            return Gate::forUser($user)->inspect($action, $arguments);
        }

        if ($hasPolicyMethod) {
            return Gate::forUser($user)->inspect($action, $arguments);
        }

        if (Filament::isAuthorizationStrict()) {
            throw new LogicException(self::missingPolicyMessage($subject, $methodName, $policy, abilityChecked: false));
        }

        return Gate::forUser($user)->inspect($action, $arguments);
    }

    private static function missingPolicyMessage(
        string $subject,
        string $methodName,
        mixed $policy,
        bool $abilityChecked,
    ): string {
        $policyClass = match (true) {
            is_string($policy) => $policy,
            is_object($policy) => $policy::class,
            default => null,
        };

        if (blank($policyClass)) {
            return $abilityChecked
                ? "Strict authorization mode is enabled, but no ability [{$methodName}] or policy with method [{$methodName}()] was found for [{$subject}]. Run 'php artisan guardian:policies' to generate it."
                : "Strict authorization mode is enabled, but no policy was found for [{$subject}]. Run 'php artisan guardian:policies' to generate it.";
        }

        return $abilityChecked
            ? "Strict authorization mode is enabled, but no ability [{$methodName}] or [{$methodName}()] method was found on [{$policyClass}]."
            : "Strict authorization mode is enabled, but no [{$methodName}()] method was found on [{$policyClass}].";
    }
}
