<?php

declare(strict_types=1);

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Waguilar\FilamentGuardian\Base\Roles\RelationManagers\BaseUsersRelationManager;
use Waguilar\FilamentGuardian\Facades\Guardian;
use Waguilar\FilamentGuardian\FilamentGuardianPlugin;
use Waguilar\FilamentGuardian\Resources\Roles\Pages\CreateRole;
use Waguilar\FilamentGuardian\Resources\Roles\Pages\EditRole;
use Waguilar\FilamentGuardian\Tests\Fixtures\Models\User;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Filament::setCurrentPanel('admin');

    foreach (['ViewAny:User', 'View:User', 'Delete:User'] as $name) {
        Permission::create(['name' => $name]);
    }

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    Gate::before(fn (): bool => true);

    $this->actor = User::create(['name' => 'Manager', 'email' => 'manager@example.com', 'password' => 'password']);
    $this->actor->assignRole(Role::create(['name' => 'Manager'])->givePermissionTo(['ViewAny:User', 'View:User']));

    $this->other = User::create(['name' => 'Other', 'email' => 'other@example.com', 'password' => 'password']);

    actingAs($this->actor);
});

it('refuses to grant a permission the actor lacks when editing a role', function () {
    $role = Role::create(['name' => 'Editor'])->givePermissionTo('ViewAny:User');

    Livewire::test(EditRole::class, ['record' => $role->getRouteKey()])
        ->fillForm(['resource_user_permissions' => ['ViewAny:User', 'Delete:User']])
        ->call('save')
        ->assertNotified(__('filament-guardian::filament-guardian.privilege_escalation.cannot_grant'));

    expect($role->fresh()->permissions->pluck('name')->all())->toBe(['ViewAny:User']);
});

it('refuses to grant a permission the actor lacks when creating a role', function () {
    Livewire::test(CreateRole::class)
        ->fillForm(['name' => 'Editor', 'resource_user_permissions' => ['ViewAny:User', 'Delete:User']])
        ->call('create')
        ->assertNotified(__('filament-guardian::filament-guardian.privilege_escalation.cannot_grant'));

    expect(Role::query()->where('name', 'Editor')->exists())->toBeFalse();
});

it('creates a role with permissions the actor holds', function () {
    Livewire::test(CreateRole::class)
        ->fillForm(['name' => 'Editor', 'resource_user_permissions' => ['ViewAny:User']])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Role::findByName('Editor')->permissions->pluck('name')->all())->toBe(['ViewAny:User']);
});

it('allows unticking while the role keeps a permission the actor lacks', function () {
    $role = Role::create(['name' => 'Editor'])->givePermissionTo(['ViewAny:User', 'Delete:User']);

    Livewire::test(EditRole::class, ['record' => $role->getRouteKey()])
        ->fillForm(['resource_user_permissions' => ['Delete:User']])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($role->fresh()->permissions->pluck('name')->all())->toBe(['Delete:User']);
});

it('refuses to attach users to a role holding a permission the actor lacks', function (string $user) {
    $role = Role::create(['name' => 'Editor'])->givePermissionTo('Delete:User');

    Livewire::test(BaseUsersRelationManager::class, ['ownerRecord' => $role, 'pageClass' => EditRole::class])
        ->callAction(TestAction::make('attach')->table(), data: ['recordId' => [$this->{$user}->getKey()]])
        ->assertNotified(__('filament-guardian::filament-guardian.privilege_escalation.cannot_attach'));

    expect($role->users()->count())->toBe(0);
})->with(['another user' => 'other', 'the actor' => 'actor']);

it('attaches users to a role holding only permissions the actor has', function () {
    $role = Role::create(['name' => 'Viewer'])->givePermissionTo('ViewAny:User');

    Livewire::test(BaseUsersRelationManager::class, ['ownerRecord' => $role, 'pageClass' => EditRole::class])
        ->callAction(TestAction::make('attach')->table(), data: ['recordId' => [$this->other->getKey()]]);

    expect($role->users()->pluck('id')->all())->toBe([$this->other->getKey()]);
});

it('lets a super admin grant any permission and attach to any role', function () {
    $this->actor->assignRole(Role::create(['name' => Guardian::getSuperAdminRoleName()]));
    $role = Role::create(['name' => 'Editor'])->givePermissionTo('Delete:User');

    Livewire::test(EditRole::class, ['record' => $role->getRouteKey()])
        ->fillForm(['resource_user_permissions' => ['Delete:User', 'View:User']])
        ->call('save')
        ->assertHasNoFormErrors();

    Livewire::test(BaseUsersRelationManager::class, ['ownerRecord' => $role, 'pageClass' => EditRole::class])
        ->callAction(TestAction::make('attach')->table(), data: ['recordId' => [$this->other->getKey()]]);

    expect($role->fresh()->permissions->pluck('name')->sort()->values()->all())->toBe(['Delete:User', 'View:User'])
        ->and($role->users()->count())->toBe(1);
});

it('grants any permission when the plugin opts out', function () {
    /** @var FilamentGuardianPlugin $plugin */
    $plugin = Filament::getPanel('admin')->getPlugin('filament-guardian');
    $plugin->preventPrivilegeEscalation(false);

    $role = Role::create(['name' => 'Editor']);

    Livewire::test(EditRole::class, ['record' => $role->getRouteKey()])
        ->fillForm(['resource_user_permissions' => ['Delete:User']])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($role->fresh()->permissions->pluck('name')->all())->toBe(['Delete:User']);
});

it('attaches to any role when the config opts out', function () {
    config(['filament-guardian.prevent_privilege_escalation' => false]);

    $role = Role::create(['name' => 'Editor'])->givePermissionTo('Delete:User');

    Livewire::test(BaseUsersRelationManager::class, ['ownerRecord' => $role, 'pageClass' => EditRole::class])
        ->callAction(TestAction::make('attach')->table(), data: ['recordId' => [$this->actor->getKey()]]);

    expect($role->users()->count())->toBe(1);
});
