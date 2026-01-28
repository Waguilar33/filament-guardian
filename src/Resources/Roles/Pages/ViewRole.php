<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian\Resources\Roles\Pages;

use Waguilar\FilamentGuardian\Base\Roles\Pages\BaseViewRole;
use Waguilar\FilamentGuardian\Resources\Roles\RoleResource;

class ViewRole extends BaseViewRole
{
    protected static string $resource = RoleResource::class;
}
