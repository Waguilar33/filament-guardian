<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian\Base\Roles\RelationManagers;

use BackedEnum;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Waguilar\FilamentGuardian\Base\Roles\Tables\BaseUsersTable;

class BaseUsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    protected static ?string $recordTitleAttribute = 'name';

    protected static BackedEnum | string | null $icon = Heroicon::OutlinedUsers;

    public function table(Table $table): Table
    {
        return BaseUsersTable::configure($table);
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('filament-guardian::filament-guardian.roles.relations.users.title');
    }

    public function isReadOnly(): bool
    {
        return false;
    }
}
