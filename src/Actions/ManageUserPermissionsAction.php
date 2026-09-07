<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian\Actions;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Spatie\Permission\Contracts\Permission;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Traits\HasRoles;
use Waguilar\FilamentGuardian\Facades\Guardian;
use Waguilar\FilamentGuardian\Schemas\PermissionsSchemaBuilder;
use Waguilar\FilamentGuardian\Support\RolePermissionData;

/**
 * Table action for managing user direct permissions.
 *
 * This action opens a modal showing only permissions NOT inherited from roles.
 * Users can add additional direct permissions on top of their role permissions.
 *
 * Every read and write is scoped to the current panel's auth guard. Permissions
 * belonging to other guards are neither shown, nor counted as role-inherited,
 * nor touched when the modal is saved.
 */
final class ManageUserPermissionsAction
{
    public static function make(): Action
    {
        return Action::make('managePermissions')
            ->label(__('filament-guardian::filament-guardian.users.permissions.action_label'))
            ->icon(Heroicon::OutlinedKey)
            ->color('gray')
            ->visible(fn (Model $record): bool => ! Guardian::userIsSuperAdmin($record))
            ->slideOver()
            ->modalWidth('4xl')
            ->modalHeading(__('filament-guardian::filament-guardian.users.permissions.modal_heading'))
            ->modalDescription(function (Model $record): string {
                /** @var string|null $name */
                $name = $record->getAttribute('name');
                /** @var string|null $email */
                $email = $record->getAttribute('email');

                return collect([$name, $email])->filter()->implode(' · ');
            })
            ->fillForm(fn (Model $record): array => self::buildFormData($record))
            ->schema(fn (Model $record): array => self::buildSchema($record))
            ->action(function (array $data, Model $record): void {
                /** @var array<string, mixed> $data */
                self::syncPermissions($data, $record);
            });
    }

    /**
     * Build the form data with only direct permissions that aren't role-based.
     *
     * Since the schema only shows permissions not inherited from roles,
     * we only fill with direct permissions that can actually be edited.
     * This ensures that direct permissions which are now also role-based
     * get cleaned up when the form is saved.
     *
     * @return array<string, array<int, string>>
     */
    private static function buildFormData(Model $record): array
    {
        $guard = self::currentGuard();
        $directPermissions = self::getDirectPermissions($record, $guard);
        $roleBasedPermissions = self::getRoleBasedPermissions($record, $guard);

        // Filter out direct permissions that are now also role-based
        // This ensures they get removed when the form is saved
        $editableDirectPermissions = $directPermissions->reject(
            fn (string $permission): bool => $roleBasedPermissions->contains($permission)
        );

        $data = RolePermissionData::make();
        $formData = [];

        foreach ($data->getResources() as $subject => $resource) {
            $fieldName = 'resource_' . mb_strtolower($subject) . '_permissions';
            $resourcePermissions = array_keys($resource['options']);
            $formData[$fieldName] = $editableDirectPermissions->intersect($resourcePermissions)->values()->all();
        }

        if ($data->hasPages()) {
            $pagePermissions = $data->getPages()['permissions']->all();
            $formData['page_permissions'] = $editableDirectPermissions->intersect($pagePermissions)->values()->all();
        }

        if ($data->hasWidgets()) {
            $widgetPermissions = $data->getWidgets()['permissions']->all();
            $formData['widget_permissions'] = $editableDirectPermissions->intersect($widgetPermissions)->values()->all();
        }

        if ($data->hasCustom()) {
            $customPermissions = $data->getCustom()['permissions']->all();
            $formData['custom_permissions'] = $editableDirectPermissions->intersect($customPermissions)->values()->all();
        }

        return $formData;
    }

    /**
     * Build the schema using PermissionsSchemaBuilder in MODE_USER.
     *
     * @return array<int, mixed>
     */
    private static function buildSchema(Model $record): array
    {
        $roleBasedPermissions = self::getRoleBasedPermissions($record, self::currentGuard());

        return PermissionsSchemaBuilder::make()
            ->mode(PermissionsSchemaBuilder::MODE_USER)
            ->roleBasedPermissions($roleBasedPermissions)
            ->build();
    }

    /**
     * Sync the selected direct permissions for the current panel's guard.
     *
     * Only permissions belonging to that guard are granted or revoked. Direct
     * permissions the user holds under any other guard are left untouched --
     * Spatie's syncPermissions() detaches every guard, which would let a save
     * in one panel wipe grants made in another.
     *
     * @param  array<string, mixed>  $data
     */
    private static function syncPermissions(array $data, Model $record): void
    {
        if (! self::hasPermissionTraits($record)) {
            return;
        }

        $guard = self::currentGuard();

        /** @var Collection<int, string> $selectedNames */
        $selectedNames = collect();

        foreach ($data as $permissions) {
            if (is_array($permissions)) {
                /** @var array<int, string> $permissions */
                $selectedNames = $selectedNames->merge($permissions);
            }
        }

        /** @var class-string<SpatiePermission> $permissionClass */
        $permissionClass = app(PermissionRegistrar::class)->getPermissionClass();

        /** @var Collection<int, SpatiePermission> $selected */
        $selected = $permissionClass::query()
            ->whereIn('name', $selectedNames->unique()->values()->all())
            ->whereRaw('guard_name = ?', [$guard])
            ->get();

        /** @var Collection<int, Permission> $current */
        $current = self::directPermissionModels($record)
            ->filter(fn (Permission $permission): bool => $permission->guard_name === $guard);

        $selectedKeys = $selected->map(fn (SpatiePermission $permission): mixed => $permission->getKey())->all();
        $currentKeys = $current->map(fn (Permission $permission): mixed => $permission->getKey())->all();

        $toRevoke = $current->reject(
            fn (Permission $permission): bool => in_array($permission->getKey(), $selectedKeys, true)
        );

        $toGrant = $selected->reject(
            fn (SpatiePermission $permission): bool => in_array($permission->getKey(), $currentKeys, true)
        );

        // Grant first: an invalid grant must throw before anything is detached, or a
        // failed save leaves the user with nothing. The two sets are disjoint, so the
        // order does not change the result.
        DB::transaction(function () use ($record, $toGrant, $toRevoke): void {
            if ($toGrant->isNotEmpty()) {
                ([$record, 'givePermissionTo'])($toGrant->values()->all()); // @phpstan-ignore callable.nonCallable
            }

            if ($toRevoke->isNotEmpty()) {
                ([$record, 'revokePermissionTo'])($toRevoke->values()); // @phpstan-ignore callable.nonCallable
            }
        });

        Notification::make()
            ->success()
            ->title(__('filament-guardian::filament-guardian.users.permissions.updated'))
            ->send();
    }

    /**
     * The auth guard of the panel the modal is being used in.
     */
    private static function currentGuard(): string
    {
        $panel = Filament::getCurrentPanel() ?? throw new RuntimeException('No Filament panel is currently active.');

        return $panel->getAuthGuard();
    }

    /**
     * Permissions inherited from the user's roles under the given guard.
     *
     * Another guard's roles grant permissions this panel never checks, so they do
     * not count as inherited here.
     *
     * @return Collection<int, string>
     */
    private static function getRoleBasedPermissions(Model $record, string $guard): Collection
    {
        if (! self::hasPermissionTraits($record)) {
            /** @var Collection<int, string> $empty */
            $empty = collect();

            return $empty;
        }

        /** @var callable $getMethod */
        $getMethod = [$record, 'getPermissionsViaRoles'];

        /** @var Collection<int, Permission> $permissions */
        $permissions = $getMethod();

        /** @var Collection<int, string> $names */
        $names = $permissions
            ->filter(fn (Permission $permission): bool => $permission->guard_name === $guard)
            ->pluck('name')
            ->values();

        return $names;
    }

    /**
     * Get the user's directly assigned permissions, for the given guard only.
     *
     * @return Collection<int, string>
     */
    private static function getDirectPermissions(Model $record, string $guard): Collection
    {
        /** @var Collection<int, string> $names */
        $names = self::directPermissionModels($record)
            ->filter(fn (Permission $permission): bool => $permission->guard_name === $guard)
            ->pluck('name')
            ->values();

        return $names;
    }

    /**
     * The user's directly assigned permission models, across every guard.
     *
     * @return Collection<int, Permission>
     */
    private static function directPermissionModels(Model $record): Collection
    {
        if (! self::hasPermissionTraits($record)) {
            /** @var Collection<int, Permission> $empty */
            $empty = collect();

            return $empty;
        }

        /** @var callable $getMethod */
        $getMethod = [$record, 'getDirectPermissions'];

        /** @var Collection<int, Permission> $permissions */
        $permissions = $getMethod();

        return $permissions;
    }

    /**
     * Check if the model uses the required permission trait.
     *
     * HasRoles specifically: this action calls getDirectPermissions() and
     * getPermissionsViaRoles(), neither of which HasPermissions defines on its own.
     */
    private static function hasPermissionTraits(Model $record): bool
    {
        return in_array(HasRoles::class, class_uses_recursive($record), true);
    }
}
