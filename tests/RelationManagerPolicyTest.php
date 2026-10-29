<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Contracts\Auth\Access\Gate as GateContract;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Gate;
use Waguilar\FilamentGuardian\Tests\Fixtures\Models\User;
use Waguilar\FilamentGuardian\Tests\Fixtures\Policies\RolesPolicy;
use Waguilar\FilamentGuardian\Tests\Fixtures\RelationManagers\RolesRelationManager;
use Waguilar\FilamentGuardian\Tests\Fixtures\Resources\Users\Pages\ViewUser;

use function Pest\Laravel\actingAs;

beforeAll(fn () => class_alias(RolesPolicy::class, 'App\Policies\RolesPolicy'));

it('executes no resource page while booting', function () {
    ViewUser::$instances = 0;

    $app = $this->createApplication();

    expect(ViewUser::$instances)->toBe(0)
        ->and($app->make(GateContract::class)->policies())->not->toHaveKey(RolesRelationManager::class);

    $app->flush();
    Facade::clearResolvedInstances();
    Facade::setFacadeApplication($this->app);
    Container::setInstance($this->app);
});

it('registers a relation manager policy the first time it is authorized', function () {
    $user = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'password']);
    actingAs($user);

    expect(Gate::policies())->not->toHaveKey(RolesRelationManager::class)
        ->and(RolesRelationManager::canViewForRecord($user, ViewUser::class))->toBeTrue()
        ->and(Gate::getPolicyFor(RolesRelationManager::class))->toBeInstanceOf(RolesPolicy::class);
});

it('leaves a policy the host app registered in place', function () {
    $hostPolicy = new class
    {
        public function viewAny(mixed $user): bool
        {
            return false;
        }
    };

    Gate::policy(RolesRelationManager::class, $hostPolicy::class);

    $user = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'password']);
    actingAs($user);

    expect(RolesRelationManager::canViewForRecord($user, ViewUser::class))->toBeFalse();
});
