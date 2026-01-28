<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian\Base\Roles\Pages;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use RuntimeException;
use Spatie\Permission\Contracts\Role;
use Waguilar\FilamentGuardian\Facades\Guardian;
use Waguilar\FilamentGuardian\Support\PermissionKeyBuilder;
use Waguilar\FilamentGuardian\Support\PermissionResolver;

abstract class BaseEditRole extends EditRecord
{
    /**
     * Permissions extracted from form data to sync after save.
     *
     * @var Collection<int, string>
     */
    protected Collection $permissionsToSync;

    public function mount(int | string $record): void
    {
        parent::mount($record);

        // Redirect if attempting to edit the super-admin role
        if ($this->record instanceof Role && Guardian::isSuperAdminRole($this->record)) {
            Notification::make()
                ->warning()
                ->title(__('filament-guardian::filament-guardian.super_admin.cannot_edit'))
                ->send();

            $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
        }
    }

    /**
     * @return array<Action | ActionGroup>
     */
    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        /** @var string $url */
        $url = $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);

        return $url;
    }

    /**
     * Populate form fields with the role's current permissions.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $panel = Filament::getCurrentPanel() ?? throw new RuntimeException('No Filament panel is currently active.');
        $keyBuilder = $this->getKeyBuilder();
        $resolver = new PermissionResolver($panel, $panel->getAuthGuard(), $keyBuilder);

        $record = $this->record;
        if (! ($record instanceof Model && $record instanceof Role)) {
            return $data;
        }

        /** @var Collection<int, string> $rolePermissions */
        $rolePermissions = collect($record->permissions->pluck('name'));

        // Get categorized permissions
        $resourcePermissions = $resolver->getResourcePermissions();
        $pagePermissions = $resolver->getPagePermissions();
        $widgetPermissions = $resolver->getWidgetPermissions();
        $customPermissions = $resolver->getCustomPermissions();

        // Populate resource permission fields
        foreach ($resourcePermissions as $subject => $permissions) {
            $fieldName = 'resource_' . mb_strtolower($subject) . '_permissions';
            $selectAllName = 'select_all_resource_' . mb_strtolower($subject);

            $selected = $permissions->intersect($rolePermissions)->values()->all();
            $data[$fieldName] = $selected;
            $data[$selectAllName] = count($selected) === $permissions->count() && $permissions->count() > 0;
        }

        // Populate page permissions
        $selectedPages = $pagePermissions->intersect($rolePermissions)->values()->all();
        $data['page_permissions'] = $selectedPages;
        $data['select_all_pages'] = count($selectedPages) === $pagePermissions->count() && $pagePermissions->count() > 0;

        // Populate widget permissions
        $selectedWidgets = $widgetPermissions->intersect($rolePermissions)->values()->all();
        $data['widget_permissions'] = $selectedWidgets;
        $data['select_all_widgets'] = count($selectedWidgets) === $widgetPermissions->count() && $widgetPermissions->count() > 0;

        // Populate custom permissions
        $selectedCustom = $customPermissions->intersect($rolePermissions)->values()->all();
        $data['custom_permissions'] = $selectedCustom;
        $data['select_all_custom'] = count($selectedCustom) === $customPermissions->count() && $customPermissions->count() > 0;

        return $data;
    }

    /**
     * Extract permissions from form data before saving the role.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->permissionsToSync = $this->extractPermissions($data);

        /** @var array<string, mixed> $result */
        $result = Arr::only($data, ['name']);

        return $result;
    }

    /**
     * Sync permissions after the role is saved.
     */
    protected function afterSave(): void
    {
        $record = $this->record;

        if ($record instanceof Model && $record instanceof Role) {
            $record->syncPermissions($this->permissionsToSync->all());
        }
    }

    /**
     * Extract all permission field values from form data.
     *
     * @param  array<string, mixed>  $data
     * @return Collection<int, string>
     */
    protected function extractPermissions(array $data): Collection
    {
        $excludedKeys = [
            'name',
        ];

        /** @var Collection<int, string> $permissions */
        $permissions = collect($data)
            ->filter(fn (mixed $value, string $key): bool => ! in_array($key, $excludedKeys, true))
            ->filter(fn (mixed $value, string $key): bool => ! str_starts_with($key, 'select_all_'))
            ->filter(fn (mixed $value, string $key): bool => str_ends_with($key, '_permissions'))
            ->flatMap(fn (mixed $value): array => is_array($value) ? $value : [])
            ->filter(fn (mixed $value): bool => is_string($value))
            ->unique()
            ->values();

        return $permissions;
    }

    /**
     * Get the configured permission key builder.
     */
    protected function getKeyBuilder(): PermissionKeyBuilder
    {
        /** @var string $separator */
        $separator = config('filament-guardian.permission_key.separator', ':');

        /** @var string $case */
        $case = config('filament-guardian.permission_key.case', 'pascal');

        return new PermissionKeyBuilder($separator, $case);
    }
}
