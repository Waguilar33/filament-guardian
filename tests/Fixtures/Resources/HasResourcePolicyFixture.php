<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian\Tests\Fixtures\Resources;

use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;
use Waguilar\FilamentGuardian\Concerns\HasResourcePolicy;

/**
 * Fixture so PHPStan analyses HasResourcePolicy through a real `use` site.
 * Not instantiated, not exercised — exists purely for static analysis coverage.
 *
 * @extends resource<Model>
 */
class HasResourcePolicyFixture extends Resource
{
    use HasResourcePolicy;

    protected static ?string $model = Model::class;
}
