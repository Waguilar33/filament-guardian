<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian\Tests\Fixtures\Policies;

class RolesPolicy
{
    public function viewAny(mixed $user): bool
    {
        return true;
    }
}
