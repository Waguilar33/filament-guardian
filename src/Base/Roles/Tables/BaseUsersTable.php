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
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\PermissionRegistrar;

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
                        return self::canUpdate($livewire->getOwnerRecord());
                    })
                    // Spatie's Role::users() is a bare morphedByMany with no pivot
                    // columns declared, so Filament has none to carry and cannot write
                    // the team key. using() supplies it while leaving Filament to
                    // resolve the records and run its own before/after hooks.
                    ->using(function (BelongsToMany $relationship, Model | EloquentCollection | null $record): void {
                        if ($record === null) {
                            return;
                        }

                        $relationship->attach($record, self::attachPivotData());
                    }),
            ])
            ->recordActions([
                DetachAction::make()
                    ->authorize(function (RelationManager $livewire): bool {
                        return self::canUpdate($livewire->getOwnerRecord());
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make()
                        ->authorize(function (RelationManager $livewire): bool {
                            return self::canUpdate($livewire->getOwnerRecord());
                        }),
                ]),
            ]);
    }

    /**
     * Gate::check() resolves the default guard's user, which is nobody on a panel
     * configured with any other guard -- hiding these actions from everyone.
     */
    public static function canUpdate(Model $ownerRecord): bool
    {
        return Gate::forUser(Filament::auth()->user())->check('update', $ownerRecord);
    }

    /**
     * The pivot columns Spatie expects on a role assignment.
     *
     * @return array<string, mixed>
     */
    protected static function attachPivotData(): array
    {
        $registrar = app(PermissionRegistrar::class);

        if (! $registrar->teams) {
            return [];
        }

        /** @var string $teamKey */
        $teamKey = $registrar->teamsKey;

        return [$teamKey => getPermissionsTeamId()];
    }
}
