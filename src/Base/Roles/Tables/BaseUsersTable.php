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
use Illuminate\Auth\Access\Response;
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
                        return self::canAttach($livewire->getOwnerRecord());
                    })
                    // Spatie's Role::users() is a bare morphedByMany with no pivot
                    // columns declared, so Filament has none to carry and cannot write
                    // the team key. using() supplies it. The records come from the
                    // form state rather than an injected $record, which Filament only
                    // sets on the action for a single selection.
                    ->using(function (BelongsToMany $relationship, array $data): void {
                        $relationship->attach($data['recordId'], self::attachPivotData());
                    }),
            ])
            ->recordActions([
                DetachAction::make()
                    ->authorize(function (RelationManager $livewire): bool {
                        return self::canDetach($livewire->getOwnerRecord());
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make()
                        ->authorize(function (RelationManager $livewire): bool {
                            return self::canDetach($livewire->getOwnerRecord());
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

    public static function canAttach(Model $ownerRecord): bool
    {
        return self::canManageMembership($ownerRecord, 'attach');
    }

    public static function canDetach(Model $ownerRecord): bool
    {
        return self::canManageMembership($ownerRecord, 'detach');
    }

    /**
     * raw() rather than check(): it returns null when nothing defines the ability, so
     * a RolePolicy predating attach/detach falls back to `update`.
     */
    protected static function canManageMembership(Model $ownerRecord, string $ability): bool
    {
        /** @var mixed $response */
        $response = Gate::forUser(Filament::auth()->user())->raw($ability, $ownerRecord);

        if ($response === null) {
            return self::canUpdate($ownerRecord);
        }

        return $response instanceof Response
            ? $response->allowed()
            : (bool) $response;
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
