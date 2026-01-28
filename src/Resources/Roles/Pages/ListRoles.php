<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian\Resources\Roles\Pages;

use Waguilar\FilamentGuardian\Base\Roles\Pages\BaseListRoles;
use Waguilar\FilamentGuardian\Resources\Roles\RoleResource;

class ListRoles extends BaseListRoles
{
    protected static string $resource = RoleResource::class;
}
