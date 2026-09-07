<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian\Base\Roles\Pages;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\RelationManagers\RelationGroup;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\RelationManagers\RelationManagerConfiguration;
use Illuminate\Support\Collection;
use Spatie\Permission\Contracts\Role;
use Waguilar\FilamentGuardian\Base\Roles\Pages\Concerns\HasGuardianContentTabs;
use Waguilar\FilamentGuardian\Concerns\SyncsPermissions;
use Waguilar\FilamentGuardian\Facades\Guardian;
use Waguilar\FilamentGuardian\Support\RolePermissionData;

abstract class BaseEditRole extends EditRecord
{
    use HasGuardianContentTabs;
    use SyncsPermissions;

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

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return static::guardianPlugin()?->shouldCombineRelationManagerTabsWithContentOnEdit()
            ?? parent::hasCombinedRelationManagerTabsWithContent();
    }

    /**
     * Hide relation managers on the edit page by default. When the plugin
     * (or config) opts into combining relation manager tabs with the content
     * tab on Edit, fall through to the resource's relations so the combined
     * layout works there too.
     *
     * @return array<class-string<RelationManager> | RelationGroup | RelationManagerConfiguration>
     */
    public function getRelationManagers(): array
    {
        if (static::guardianPlugin()?->shouldCombineRelationManagerTabsWithContentOnEdit() ?? false) {
            return parent::getRelationManagers();
        }

        return [];
    }

    /**
     * Populate form fields with the role's current permissions.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        // RolePermissionData caches a resolver per panel for the request, and the
        // form schema has already built one; a second would repeat the permission
        // query and the whole reflection pass over the panel's components.
        $resolver = RolePermissionData::make()->getResolver();

        $record = $this->record;
        if (! $record instanceof Role) {
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

            $data[$fieldName] = $permissions->intersect($rolePermissions)->values()->all();
        }

        $data['page_permissions'] = $pagePermissions->intersect($rolePermissions)->values()->all();
        $data['widget_permissions'] = $widgetPermissions->intersect($rolePermissions)->values()->all();
        $data['custom_permissions'] = $customPermissions->intersect($rolePermissions)->values()->all();

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->capturePermissionsFromFormData($data);
    }

    protected function afterSave(): void
    {
        $this->syncCapturedPermissions();
    }
}
