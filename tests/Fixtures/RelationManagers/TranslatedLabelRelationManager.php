<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian\Tests\Fixtures\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;

class TranslatedLabelRelationManager extends RelationManager
{
    protected static string $relationship = 'terms';

    protected static function getPluralModelLabel(): ?string
    {
        return __('fixtures.terms');
    }
}
