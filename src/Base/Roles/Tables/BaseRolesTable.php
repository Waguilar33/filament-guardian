<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian\Base\Roles\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Spatie\Permission\Contracts\Role;
use Waguilar\FilamentGuardian\Facades\Guardian;

class BaseRolesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('filament-guardian::filament-guardian.roles.attributes.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('users_count')
                    ->label(__('filament-guardian::filament-guardian.roles.attributes.users'))
                    ->counts('users')
                    ->badge()
                    ->sortable(),
                TextColumn::make('permissions_count')
                    ->label(__('filament-guardian::filament-guardian.roles.attributes.permissions'))
                    ->counts('permissions')
                    ->badge()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('filament-guardian::filament-guardian.roles.attributes.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([

            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->hidden(fn (Role $record): bool => Guardian::isSuperAdminRole($record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->checkIfRecordIsSelectableUsing(
                fn (Role $record): bool => ! Guardian::isSuperAdminRole($record)
            );
    }
}
