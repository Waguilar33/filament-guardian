<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian\Resources\Roles\Pages;

use Waguilar\FilamentGuardian\Base\Roles\Pages\BaseEditRole;
use Waguilar\FilamentGuardian\Resources\Roles\RoleResource;

class EditRole extends BaseEditRole
{
    protected static string $resource = RoleResource::class;
}
