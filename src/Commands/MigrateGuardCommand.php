<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Spatie\Permission\Contracts\Permission as PermissionContract;
use Spatie\Permission\Contracts\Role as RoleContract;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Move roles and permissions from one auth guard to another.
 *
 * Rows are moved by rewriting guard_name in place, so every primary key stays
 * stable and the pivot tables follow automatically -- no user loses a role and
 * no role loses a permission. The two modes differ only in how they handle a
 * name that already exists under the target guard:
 *
 * - move  : abort and list the conflicts, changing nothing.
 * - merge : keep the target row, repoint everything that referenced the source
 *           row onto it, then delete the source row.
 */
#[AsCommand(name: 'guardian:migrate-guard', description: 'Move roles and permissions from one auth guard to another')]
class MigrateGuardCommand extends Command
{
    /** @var string */
    public $signature = 'guardian:migrate-guard
        {--from= : The guard to move roles and permissions away from}
        {--to= : The guard to move them to}
        {--mode=move : How to handle names that already exist under the target guard: move|merge}
        {--only= : Limit the migration to permissions or roles}
        {--dry-run : Report what would change without writing anything}
        {--allow-unregistered-guard : Proceed even if --to is missing from config/auth.php}
        {--force : Skip the confirmation prompt}';

    /** @var string */
    public $description = 'Move roles and permissions from one auth guard to another';

    protected string $from;

    protected string $to;

    protected string $mode;

    /** @var array<string, int> */
    protected array $stats = [
        'permissions_moved' => 0,
        'permissions_merged' => 0,
        'roles_moved' => 0,
        'roles_merged' => 0,
        'links_deduplicated' => 0,
    ];

    public function handle(): int
    {
        if (! $this->readOptions()) {
            return self::FAILURE;
        }

        $permissions = $this->shouldMigrate('permissions') ? $this->sourcePermissions() : new EloquentCollection;
        $roles = $this->shouldMigrate('roles') ? $this->sourceRoles() : new EloquentCollection;

        if ($permissions->isEmpty() && $roles->isEmpty()) {
            $this->components->warn("Nothing to migrate: no roles or permissions use guard '{$this->from}'.");

            return self::SUCCESS;
        }

        $conflicts = $this->findConflicts($permissions, $roles);

        if ($this->mode === 'move' && $conflicts !== []) {
            $this->reportConflicts($conflicts);

            // A dry run wrote nothing, so reporting the conflicts is its result, not a failure.
            return $this->option('dry-run') ? self::SUCCESS : self::FAILURE;
        }

        $this->reportPlan($permissions, $roles, $conflicts);

        if ($this->option('dry-run')) {
            $this->components->info('Dry run -- nothing was written.');

            return self::SUCCESS;
        }

        if (! $this->confirmToProceed($permissions->count() + $roles->count())) {
            return self::FAILURE;
        }

        try {
            DB::transaction(function () use ($permissions, $roles): void {
                // Permissions first: merging them can collapse two role_has_permissions
                // rows into one, a collision only the roles pass can then clean up.
                $this->migratePermissions($permissions);
                $this->migrateRoles($roles);
            });
        } catch (RuntimeException $exception) {
            // Everything mutating ran inside the transaction, so nothing was written.
            $this->newLine();
            $this->components->error($exception->getMessage());
            $this->components->info('The database was not changed.');

            return self::FAILURE;
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->displaySummary();

        return self::SUCCESS;
    }

    protected function readOptions(): bool
    {
        /** @var string|null $from */
        $from = $this->option('from');
        /** @var string|null $to */
        $to = $this->option('to');
        /** @var string $mode */
        $mode = $this->option('mode');

        if (blank($from) || blank($to)) {
            $this->components->error('Both --from and --to are required.');

            return false;
        }

        if ($from === $to) {
            $this->components->error('--from and --to must be different guards.');

            return false;
        }

        if (! in_array($mode, ['move', 'merge'], true)) {
            $this->components->error("Unknown mode '{$mode}'. Use --mode=move or --mode=merge.");

            return false;
        }

        /** @var string $only */
        $only = $this->option('only') ?? '';

        if (filled($only) && ! in_array($only, ['permissions', 'roles'], true)) {
            $this->components->error("Unknown --only value '{$only}'. Use --only=permissions or --only=roles.");

            return false;
        }

        $this->from = $from;
        $this->to = $to;
        $this->mode = $mode;

        /** @var array<string, mixed>|null $guards */
        $guards = config('auth.guards');

        if ($guards !== null && ! array_key_exists($to, $guards)) {
            // Migrating onto a guard Laravel does not know about leaves hasPermissionTo()
            // and assignRole() throwing, so refuse rather than report success.
            $this->components->error(
                "Guard '{$to}' is not configured in config/auth.php. Register it there first, "
                . 'or pass --allow-unregistered-guard if you are adding it in the same deploy.'
            );

            if (! $this->option('allow-unregistered-guard')) {
                return false;
            }

            $this->components->warn('Continuing anyway because --allow-unregistered-guard was passed.');
        }

        if (! $this->teamSchemaMatchesConfig()) {
            /** @var string $teamKey */
            $teamKey = $this->teamKey();

            $this->components->error(
                "Spatie's teams feature is " . ($this->teamsEnabled() ? 'enabled' : 'disabled')
                . ', but the roles table ' . ($this->teamsEnabled() ? 'has no' : 'has a')
                . " '{$teamKey}' column. Migrating under that mismatch would merge roles across teams. "
                . 'Run `php artisan config:clear` and check your permission migration before retrying.'
            );

            return false;
        }

        if ($this->teamsEnabled()) {
            $this->components->warn(
                'Teams are enabled. Roles are matched on (team, name) and pivot rows are de-duplicated per team.'
            );
        }

        return true;
    }

    /**
     * Whether Spatie's teams setting agrees with the roles table's schema.
     *
     * Toggling the config after the migration has run -- or a stale config cache --
     * silently drops the team filter when matching roles, which collapses two
     * different teams' roles into one. Cheap to detect, unrecoverable if missed.
     */
    protected function teamSchemaMatchesConfig(): bool
    {
        /** @var string $rolesTable */
        $rolesTable = config('permission.table_names.roles', 'roles');

        return Schema::hasColumn($rolesTable, $this->teamKey()) === $this->teamsEnabled();
    }

    protected function shouldMigrate(string $what): bool
    {
        /** @var string $only */
        $only = $this->option('only') ?? '';

        return blank($only) || $only === $what;
    }

    /** @return EloquentCollection<int, Model&PermissionContract> */
    protected function sourcePermissions(): EloquentCollection
    {
        /** @var class-string<Model&PermissionContract> $permissionClass */
        $permissionClass = app(PermissionRegistrar::class)->getPermissionClass();

        /** @var EloquentCollection<int, Model&PermissionContract> */
        // whereRaw because the model class is resolved at runtime: PHPStan runs with
        // checkModelProperties, which cannot verify a column against a Model it cannot
        // narrow. Every binding here is parameterised.
        return $permissionClass::query()->whereRaw('guard_name = ?', [$this->from])->get();
    }

    /** @return EloquentCollection<int, Model&RoleContract> */
    protected function sourceRoles(): EloquentCollection
    {
        /** @var class-string<Model&RoleContract> $roleClass */
        $roleClass = app(PermissionRegistrar::class)->getRoleClass();

        /** @var EloquentCollection<int, Model&RoleContract> */
        return $roleClass::query()->whereRaw('guard_name = ?', [$this->from])->get();
    }

    /**
     * @param  EloquentCollection<int, Model&PermissionContract>  $permissions
     * @param  EloquentCollection<int, Model&RoleContract>  $roles
     * @return array{permissions?: array<int, string>, roles?: array<int, string>}
     */
    protected function findConflicts(EloquentCollection $permissions, EloquentCollection $roles): array
    {
        $conflicts = [];

        $permissionConflicts = $permissions
            ->filter(fn (Model $permission): bool => $this->findTargetPermission($permission) !== null)
            ->map(function (Model $permission): string {
                /** @var string $name */
                $name = $permission->getAttribute('name');

                return $name;
            })
            ->values()
            ->all();

        $roleConflicts = $roles
            ->filter(fn (Model $role): bool => $this->findTargetRole($role) !== null)
            ->map(fn (Model $role): string => $this->describeRole($role))
            ->values()
            ->all();

        if ($permissionConflicts !== []) {
            $conflicts['permissions'] = $permissionConflicts;
        }

        if ($roleConflicts !== []) {
            $conflicts['roles'] = $roleConflicts;
        }

        return $conflicts;
    }

    /**
     * @param  Model&PermissionContract  $source
     * @return (Model&PermissionContract)|null
     */
    protected function findTargetPermission(Model $source): ?Model
    {
        /** @var class-string<Model&PermissionContract> $permissionClass */
        $permissionClass = app(PermissionRegistrar::class)->getPermissionClass();

        /** @var (Model&PermissionContract)|null */
        return $permissionClass::query()
            ->whereRaw('name = ?', [$source->getAttribute('name')])
            ->whereRaw('guard_name = ?', [$this->to])
            ->first();
    }

    /**
     * @param  Model&RoleContract  $source
     * @return (Model&RoleContract)|null
     */
    protected function findTargetRole(Model $source): ?Model
    {
        /** @var class-string<Model&RoleContract> $roleClass */
        $roleClass = app(PermissionRegistrar::class)->getRoleClass();

        $query = $roleClass::query()
            ->whereRaw('name = ?', [$source->getAttribute('name')])
            ->whereRaw('guard_name = ?', [$this->to]);

        if ($this->teamsEnabled()) {
            $teamKey = $this->teamKey();
            $query->where($teamKey, $source->getAttribute($teamKey));
        }

        /** @var (Model&RoleContract)|null */
        return $query->first();
    }

    /**
     * @param  EloquentCollection<int, Model&PermissionContract>  $permissions
     */
    protected function migratePermissions(EloquentCollection $permissions): void
    {
        foreach ($permissions as $source) {
            $target = $this->findTargetPermission($source);

            if ($this->isSameRow($source, $target)) {
                throw new RuntimeException(
                    "Guard '{$this->from}' and '{$this->to}' resolve to the same rows in the database "
                    . "(permission '{$source->name}' matched itself). This usually means the database "
                    . 'compares guard names case-insensitively or ignores trailing spaces. Use the exact '
                    . 'guard names as they are stored.'
                );
            }

            if ($target === null) {
                $source->setAttribute('guard_name', $this->to);
                $source->save();

                $this->stats['permissions_moved']++;

                continue;
            }

            /** @var string $sourceName */
            $sourceName = $source->getAttribute('name');
            $this->guardMergeIsAllowed($sourceName);
            $this->repointPermission($source, $target);
            $this->deleteRow($source);

            $this->stats['permissions_merged']++;
        }
    }

    /**
     * Whether the "target" the lookup returned is in fact the source row.
     *
     * findTarget*() matches in SQL while --from/--to are compared in PHP, so a
     * database that compares strings case-insensitively (MySQL's default) or
     * ignores trailing spaces can hand back the very row being migrated. Merging a
     * row into itself deletes all of its links and then the row.
     */
    protected function isSameRow(Model $source, ?Model $target): bool
    {
        return $target !== null && $target->getKey() === $source->getKey();
    }

    /**
     * Abort if a merge is about to happen in a mode that did not ask for one.
     *
     * The pre-flight conflict scan runs before the transaction opens, so a row
     * created in between would otherwise be merged silently under --mode=move.
     */
    protected function guardMergeIsAllowed(string $name): void
    {
        if ($this->mode === 'move') {
            throw new RuntimeException(
                "'{$name}' already exists under guard '{$this->to}'. It was not there when the run started, "
                . 'so something else wrote to the database. Nothing was changed. Re-run to see the current '
                . 'plan, or use --mode=merge.'
            );
        }
    }

    /**
     * forceDelete() rather than delete(): on a plain model the two are identical, but
     * a custom Role or Permission using SoftDeletes would otherwise keep occupying the
     * unique index, and Spatie's deleting hook skips its pivot cleanup unless
     * isForceDeleting() is true.
     */
    protected function deleteRow(Model $row): void
    {
        $row->forceDelete();
    }

    /**
     * Point every reference to the source permission at the target permission,
     * dropping the source's row wherever the target already occupies the slot.
     */
    /**
     * @param  Model&PermissionContract  $source
     * @param  Model&PermissionContract  $target
     */
    protected function repointPermission(Model $source, Model $target): void
    {
        $permissionKey = $this->permissionPivotKey();

        $this->dedupe(
            $this->roleHasPermissionsTable(),
            $permissionKey,
            $source->getKey(),
            $target->getKey(),
            [$this->rolePivotKey()],
        );

        DB::table($this->roleHasPermissionsTable())
            ->where($permissionKey, $source->getKey())
            ->update([$permissionKey => $target->getKey()]);

        $modelColumns = [$this->morphKey(), 'model_type'];

        if ($this->teamsEnabled()) {
            $modelColumns[] = $this->teamKey();
        }

        $this->dedupe(
            $this->modelHasPermissionsTable(),
            $permissionKey,
            $source->getKey(),
            $target->getKey(),
            $modelColumns,
        );

        DB::table($this->modelHasPermissionsTable())
            ->where($permissionKey, $source->getKey())
            ->update([$permissionKey => $target->getKey()]);
    }

    /**
     * @param  EloquentCollection<int, Model&RoleContract>  $roles
     */
    protected function migrateRoles(EloquentCollection $roles): void
    {
        foreach ($roles as $source) {
            $target = $this->findTargetRole($source);

            if ($this->isSameRow($source, $target)) {
                throw new RuntimeException(
                    "Guard '{$this->from}' and '{$this->to}' resolve to the same rows in the database "
                    . "(role '{$source->name}' matched itself). This usually means the database compares "
                    . 'guard names case-insensitively or ignores trailing spaces. Use the exact guard '
                    . 'names as they are stored.'
                );
            }

            if ($target === null) {
                $source->setAttribute('guard_name', $this->to);
                $source->save();

                $this->stats['roles_moved']++;

                continue;
            }

            $this->guardMergeIsAllowed($this->describeRole($source));
            $this->repointRole($source, $target);
            $this->deleteRow($source);

            $this->stats['roles_merged']++;
        }
    }

    /**
     * Point every reference to the source role at the target role.
     *
     * The role_has_permissions dedupe here is not redundant with the permissions
     * pass: merging permissions can leave both roles pointing at the same
     * permission id, which only collides once the roles themselves collapse.
     */
    /**
     * @param  Model&RoleContract  $source
     * @param  Model&RoleContract  $target
     */
    protected function repointRole(Model $source, Model $target): void
    {
        $roleKey = $this->rolePivotKey();

        $this->dedupe(
            $this->roleHasPermissionsTable(),
            $roleKey,
            $source->getKey(),
            $target->getKey(),
            [$this->permissionPivotKey()],
        );

        DB::table($this->roleHasPermissionsTable())
            ->where($roleKey, $source->getKey())
            ->update([$roleKey => $target->getKey()]);

        $modelColumns = [$this->morphKey(), 'model_type'];

        if ($this->teamsEnabled()) {
            $modelColumns[] = $this->teamKey();
        }

        $this->dedupe(
            $this->modelHasRolesTable(),
            $roleKey,
            $source->getKey(),
            $target->getKey(),
            $modelColumns,
        );

        DB::table($this->modelHasRolesTable())
            ->where($roleKey, $source->getKey())
            ->update([$roleKey => $target->getKey()]);
    }

    /**
     * Delete source pivot rows that would collide with an existing target row
     * once the foreign key is repointed.
     *
     * One statement, whatever the row count -- the repoint that follows is a
     * single bulk UPDATE, and this has no reason to be chattier than that.
     *
     * The inner SELECT is wrapped in a derived table on purpose: MySQL refuses to
     * read the table it is deleting from in a plain subquery (error 1093), and
     * the wrapper forces it to materialise first. Portable to SQLite and Postgres.
     *
     * @param  array<int, string>  $matchColumns  The rest of the row's primary key.
     */
    protected function dedupe(string $table, string $foreignKey, mixed $sourceId, mixed $targetId, array $matchColumns): void
    {
        $connection = DB::connection();
        $grammar = $connection->getQueryGrammar();

        $wrappedTable = $grammar->wrapTable($table);
        $wrappedKey = $grammar->wrap($foreignKey);
        $wrappedColumns = implode(', ', array_map(
            fn (string $column): string => $grammar->wrap($column),
            $matchColumns,
        ));

        $this->stats['links_deduplicated'] += $connection->delete(
            "delete from {$wrappedTable} where {$wrappedKey} = ? and ({$wrappedColumns}) in ("
            . "select * from (select {$wrappedColumns} from {$wrappedTable} where {$wrappedKey} = ?) as "
            . $grammar->wrap('guardian_duplicates')
            . ')',
            [$sourceId, $targetId],
        );
    }

    /**
     * @param  EloquentCollection<int, Model&PermissionContract>  $permissions
     * @param  EloquentCollection<int, Model&RoleContract>  $roles
     * @param  array{permissions?: array<int, string>, roles?: array<int, string>}  $conflicts
     */
    protected function reportPlan(EloquentCollection $permissions, EloquentCollection $roles, array $conflicts): void
    {
        $this->components->info("Migrating guard '{$this->from}' to '{$this->to}' (mode: {$this->mode}).");
        $this->newLine();

        $this->components->twoColumnDetail(
            '<fg=bright-blue>Permissions</>',
            $permissions->count() . ' found, ' . count($conflicts['permissions'] ?? []) . ' already in target'
        );

        $this->components->twoColumnDetail(
            '<fg=bright-blue>Roles</>',
            $roles->count() . ' found, ' . count($conflicts['roles'] ?? []) . ' already in target'
        );

        if ($conflicts !== []) {
            $this->newLine();
            $this->components->warn('These will be merged into the existing target rows, then deleted:');

            foreach ($conflicts['permissions'] ?? [] as $name) {
                $this->line("  <fg=gray>permission</> {$name}");
            }

            foreach ($conflicts['roles'] ?? [] as $name) {
                $this->line("  <fg=gray>role</> {$name}");
            }
        }

        $this->newLine();
    }

    /**
     * @param  array{permissions?: array<int, string>, roles?: array<int, string>}  $conflicts
     */
    protected function reportConflicts(array $conflicts): void
    {
        $this->components->error(
            "These names already exist under guard '{$this->to}', so nothing was changed."
        );

        foreach ($conflicts['permissions'] ?? [] as $name) {
            $this->line("  <fg=gray>permission</> {$name}");
        }

        foreach ($conflicts['roles'] ?? [] as $name) {
            $this->line("  <fg=gray>role</> {$name}");
        }

        $this->newLine();
        $this->components->info('Re-run with --mode=merge to fold them into the existing target rows.');
    }

    protected function confirmToProceed(int $count): bool
    {
        if ($this->option('force')) {
            return true;
        }

        $confirmed = $this->components->confirm(
            "Move {$count} row(s) from guard '{$this->from}' to '{$this->to}'?",
            false
        );

        if (! $confirmed) {
            $this->components->warn('Aborted.');
        }

        return $confirmed;
    }

    protected function displaySummary(): void
    {
        $this->newLine();
        $this->components->info('Summary.');

        /** @var string $only */
        $only = $this->option('only') ?? '';

        if (filled($only)) {
            $other = $only === 'permissions' ? 'roles' : 'permissions';

            $this->components->warn(
                "Only {$only} were migrated. Any {$other} still on guard '{$this->from}' are now linked to "
                . "{$only} on guard '{$this->to}'. Spatie throws on a cross-guard link, so run the "
                . "other half (--only={$other}) before using the application."
            );
        }

        $this->components->twoColumnDetail(
            '<fg=bright-blue>Permissions</>',
            "{$this->stats['permissions_moved']} moved, {$this->stats['permissions_merged']} merged"
        );

        $this->components->twoColumnDetail(
            '<fg=bright-blue>Roles</>',
            "{$this->stats['roles_moved']} moved, {$this->stats['roles_merged']} merged"
        );

        if ($this->stats['links_deduplicated'] > 0) {
            $this->components->twoColumnDetail(
                '<fg=bright-blue>Duplicate links removed</>',
                (string) $this->stats['links_deduplicated']
            );
        }
    }

    /** @param  Model&RoleContract  $role */
    protected function describeRole(Model $role): string
    {
        /** @var string $name */
        $name = $role->getAttribute('name');

        if (! $this->teamsEnabled()) {
            return $name;
        }

        $teamKey = $this->teamKey();
        /** @var int|string|null $team */
        $team = $role->getAttribute($teamKey);

        return "{$name} ({$teamKey}=" . ($team ?? 'null') . ')';
    }

    protected function teamsEnabled(): bool
    {
        return app(PermissionRegistrar::class)->teams;
    }

    protected function teamKey(): string
    {
        return app(PermissionRegistrar::class)->teamsKey;
    }

    protected function morphKey(): string
    {
        /** @var string */
        return config('permission.column_names.model_morph_key', 'model_id');
    }

    protected function rolePivotKey(): string
    {
        return app(PermissionRegistrar::class)->pivotRole;
    }

    protected function permissionPivotKey(): string
    {
        return app(PermissionRegistrar::class)->pivotPermission;
    }

    protected function roleHasPermissionsTable(): string
    {
        /** @var string */
        return config('permission.table_names.role_has_permissions', 'role_has_permissions');
    }

    protected function modelHasPermissionsTable(): string
    {
        /** @var string */
        return config('permission.table_names.model_has_permissions', 'model_has_permissions');
    }

    protected function modelHasRolesTable(): string
    {
        /** @var string */
        return config('permission.table_names.model_has_roles', 'model_has_roles');
    }
}
