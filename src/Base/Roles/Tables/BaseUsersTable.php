<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian\Base\Roles\Tables;

use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Facades\Filament;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Gate;

class BaseUsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('filament-guardian::filament-guardian.roles.relations.users.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label(__('filament-guardian::filament-guardian.roles.relations.users.email'))
                    ->searchable()
                    ->sortable(),
            ])
            ->headerActions([
                AttachAction::make()
                    ->preloadRecordSelect()
                    ->multiple()
                    ->authorize(function (RelationManager $livewire): bool {
                        return Gate::check('update', $livewire->getOwnerRecord());
                    })
                    ->action(function (array $data, Table $table): void {
                        /** @var array<int|string> $userIds */
                        $userIds = (array) ($data['recordId'] ?? $data['recordIds'] ?? []);

                        $pivotData = [];
                        $tenant = Filament::getTenant();

                        if ($tenant !== null) {
                            /** @var string $teamForeignKey */
                            $teamForeignKey = config('permission.column_names.team_foreign_key', 'team_id');
                            $pivotData[$teamForeignKey] = $tenant->getKey();
                        }

                        /** @var BelongsToMany<*, *, *> $relationship */
                        $relationship = $table->getRelationship();
                        $relationship->attach($userIds, $pivotData);
                    }),
            ])
            ->recordActions([
                DetachAction::make()
                    ->authorize(function (RelationManager $livewire): bool {
                        return Gate::check('update', $livewire->getOwnerRecord());
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make()
                        ->authorize(function (RelationManager $livewire): bool {
                            return Gate::check('update', $livewire->getOwnerRecord());
                        }),
                ]),
            ]);
    }
}
