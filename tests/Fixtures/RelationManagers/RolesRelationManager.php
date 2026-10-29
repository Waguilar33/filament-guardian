<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian\Tests\Fixtures\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Waguilar\FilamentGuardian\Concerns\HasRelationManagerPolicy;

class RolesRelationManager extends RelationManager
{
    use HasRelationManagerPolicy;

    protected static string $relationship = 'roles';
}
