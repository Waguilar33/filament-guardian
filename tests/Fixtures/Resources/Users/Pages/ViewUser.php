<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian\Tests\Fixtures\Resources\Users\Pages;

use Filament\Resources\Pages\ViewRecord;
use Waguilar\FilamentGuardian\Tests\Fixtures\RelationManagers\RolesRelationManager;
use Waguilar\FilamentGuardian\Tests\Fixtures\Resources\Users\UserResource;

class ViewUser extends ViewRecord
{
    public static int $instances = 0;

    protected static string $resource = UserResource::class;

    public function __construct()
    {
        static::$instances++;
    }

    protected function getAllRelationManagers(): array
    {
        return [RolesRelationManager::class];
    }
}
