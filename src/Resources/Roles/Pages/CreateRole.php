<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian\Resources\Roles\Pages;

use Waguilar\FilamentGuardian\Base\Roles\Pages\BaseCreateRole;
use Waguilar\FilamentGuardian\Resources\Roles\RoleResource;

class CreateRole extends BaseCreateRole
{
    protected static string $resource = RoleResource::class;
}
