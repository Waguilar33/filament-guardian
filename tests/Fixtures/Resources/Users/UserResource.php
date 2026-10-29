<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian\Tests\Fixtures\Resources\Users;

use Filament\Resources\Resource;
use Waguilar\FilamentGuardian\Tests\Fixtures\Models\User;
use Waguilar\FilamentGuardian\Tests\Fixtures\Resources\Users\Pages\ViewUser;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    public static function getPages(): array
    {
        return [
            'view' => ViewUser::route('/{record}'),
        ];
    }
}
