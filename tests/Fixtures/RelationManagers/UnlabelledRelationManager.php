<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian\Tests\Fixtures\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;

class UnlabelledRelationManager extends RelationManager
{
    protected static string $relationship = 'terms';
}
