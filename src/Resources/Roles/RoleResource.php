<?php

declare(strict_types=1);

namespace Waguilar\FilamentGuardian\Resources\Roles;

use Waguilar\FilamentGuardian\Base\Roles\BaseRoleResource;
use Waguilar\FilamentGuardian\Resources\Roles\Pages\CreateRole;
use Waguilar\FilamentGuardian\Resources\Roles\Pages\EditRole;
use Waguilar\FilamentGuardian\Resources\Roles\Pages\ListRoles;
use Waguilar\FilamentGuardian\Resources\Roles\Pages\ViewRole;

class RoleResource extends BaseRoleResource
{
    public static function getPages(): array
    {
        return [
            'index' => ListRoles::route('/'),
            'create' => CreateRole::route('/create'),
            'view' => ViewRole::route('/{record}'),
            'edit' => EditRole::route('/{record}/edit'),
        ];
    }
}
