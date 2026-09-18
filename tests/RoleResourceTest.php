<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Waguilar\FilamentGuardian\Base\Roles\RelationManagers\BaseUsersRelationManager;
use Waguilar\FilamentGuardian\Resources\Roles\Pages\ViewRole;
use Waguilar\FilamentGuardian\Resources\Roles\RoleResource;
use Waguilar\FilamentGuardian\Tests\Fixtures\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('registers the role resource on a panel using the plugin', function () {
    expect(Filament::getPanel('admin')->getResources())->toContain(RoleResource::class);
});

it('renders every role resource page', function () {
    $role = Role::create(['name' => 'Editor']);

    actingAs(User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'password']));

    get(RoleResource::getUrl('index'))->assertOk();
    get(RoleResource::getUrl('create'))->assertOk();
    get(RoleResource::getUrl('view', ['record' => $role]))->assertOk();
    get(RoleResource::getUrl('edit', ['record' => $role]))->assertOk();

    Livewire::test(BaseUsersRelationManager::class, ['ownerRecord' => $role, 'pageClass' => ViewRole::class])->assertOk();
});
