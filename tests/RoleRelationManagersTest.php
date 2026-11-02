<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Waguilar\FilamentGuardian\Base\Roles\RelationManagers\BaseUsersRelationManager;
use Waguilar\FilamentGuardian\Resources\Roles\Pages\ViewRole;
use Waguilar\FilamentGuardian\Resources\Roles\RoleResource;
use Waguilar\FilamentGuardian\Support\PermissionKeyBuilder;
use Waguilar\FilamentGuardian\Support\PermissionResolver;
use Waguilar\FilamentGuardian\Support\RelationManagerDiscoverer;

it('registers role relation managers under named keys', function () {
    expect(array_keys(RoleResource::getRelations()))->toBe(['users']);

    $managers = (new ReflectionMethod(ViewRole::class, 'getAllRelationManagers'))->invoke(new ViewRole);

    expect($managers)->not->toBeEmpty()
        ->and(array_filter(array_keys($managers), is_string(...)))->toHaveCount(count($managers));
});

it('discovers the users relation manager under the same permission subjects', function () {
    $panel = Filament::getPanel('admin');

    $resolver = new PermissionResolver($panel, $panel->getAuthGuard(), app(PermissionKeyBuilder::class));

    expect(RelationManagerDiscoverer::collectClasses(RoleResource::class))->toBe([BaseUsersRelationManager::class])
        ->and($resolver->getResourceLabels()->all())->toBe(['User' => 'Users', 'Role' => 'Roles', 'Roles' => 'Roles']);
});
