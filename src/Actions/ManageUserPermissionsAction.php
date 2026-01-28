<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian\Actions;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Spatie\Permission\Contracts\Permission;
use Spatie\Permission\Traits\HasPermissions;
use Spatie\Permission\Traits\HasRoles;
use Waguilar\FilamentGuardian\Facades\Guardian;
use Waguilar\FilamentGuardian\Schemas\PermissionsSchemaBuilder;
use Waguilar\FilamentGuardian\Support\RolePermissionData;

/**
 * Table action for managing user direct permissions.
 *
 * This action opens a modal showing only permissions NOT inherited from roles.
 * Users can add additional direct permissions on top of their role permissions.
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
        $directPermissions = self::getDirectPermissions($record);
        $roleBasedPermissions = self::getRoleBasedPermissions($record);

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
        $roleBasedPermissions = self::getRoleBasedPermissions($record);

        return PermissionsSchemaBuilder::make()
            ->mode(PermissionsSchemaBuilder::MODE_USER)
            ->roleBasedPermissions($roleBasedPermissions)
            ->build();
    }

    /**
     * Sync the selected direct permissions.
     *
     * @param  array<string, mixed>  $data
     */
    private static function syncPermissions(array $data, Model $record): void
    {
        /** @var Collection<int, string> $selectedPermissions */
        $selectedPermissions = collect();

        foreach ($data as $permissions) {
            if (is_array($permissions)) {
                /** @var array<int, string> $permissions */
                $selectedPermissions = $selectedPermissions->merge($permissions);
            }
        }

        $directPermissions = $selectedPermissions->unique()->values()->all();

        if (self::hasPermissionTraits($record)) {
            /** @var callable $syncMethod */
            $syncMethod = [$record, 'syncPermissions'];
            $syncMethod($directPermissions);
        }

        Notification::make()
            ->success()
            ->title(__('filament-guardian::filament-guardian.users.permissions.updated'))
            ->send();
    }

    /**
     * Get permissions inherited from the user's roles.
     *
     * @return Collection<int, string>
     */
    private static function getRoleBasedPermissions(Model $record): Collection
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
        $names = $permissions->pluck('name');

        return $names;
    }

    /**
     * Get the user's directly assigned permissions.
     *
     * @return Collection<int, string>
     */
    private static function getDirectPermissions(Model $record): Collection
    {
        if (! self::hasPermissionTraits($record)) {
            /** @var Collection<int, string> $empty */
            $empty = collect();

            return $empty;
        }

        /** @var callable $getMethod */
        $getMethod = [$record, 'getDirectPermissions'];

        /** @var Collection<int, Permission> $permissions */
        $permissions = $getMethod();

        /** @var Collection<int, string> $names */
        $names = $permissions->pluck('name');

        return $names;
    }

    /**
     * Check if the model uses the required permission traits.
     */
    private static function hasPermissionTraits(Model $record): bool
    {
        $usedTraits = class_uses_recursive($record);

        return in_array(HasRoles::class, $usedTraits, true)
            || in_array(HasPermissions::class, $usedTraits, true);
    }
}
