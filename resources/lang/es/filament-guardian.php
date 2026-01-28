<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Role Resource
    |--------------------------------------------------------------------------
    */

    'roles' => [
        'label' => 'Rol',
        'plural' => 'Roles',

        'attributes' => [
            'name' => 'Nombre',
            'users' => 'Usuarios',
            'permissions' => 'Permisos',
            'created_at' => 'Creado',
            'updated_at' => 'Actualizado',
        ],

        'sections' => [
            'role' => 'Detalles del Rol',
            'permissions' => 'Permisos',
        ],

        'tabs' => [
            'resources' => 'Recursos',
            'pages' => 'Páginas',
            'widgets' => 'Widgets',
            'custom' => 'Personalizado',
        ],

        'search' => [
            'label' => 'Buscar',
            'placeholder' => 'Buscar recursos...',
        ],

        'actions' => [
            'select_all' => 'Seleccionar todos',
        ],

        'messages' => [
            'select_all' => 'Seleccionar Todo',
            'no_permissions' => 'Sin permisos asignados',
            'permissions_count' => ':count permiso asignado|:count permisos asignados',
        ],

        'relations' => [
            'users' => [
                'title' => 'Usuarios',
                'name' => 'Nombre',
                'email' => 'Correo',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Permissions
    |--------------------------------------------------------------------------
    */

    'users' => [
        'permissions' => [
            'action_label' => 'Permisos',
            'modal_heading' => 'Administrar Permisos',
            'role_permissions_title' => 'Permisos de Roles',
            'role_permissions_message' => 'Este usuario ya tiene :count permiso de sus roles asignados. Solo se muestran los permisos que se pueden asignar directamente.|Este usuario ya tiene :count permisos de sus roles asignados. Solo se muestran los permisos que se pueden asignar directamente.',
            'no_additional_permissions' => 'Todos los permisos disponibles ya están otorgados a través de los roles del usuario.',
            'updated' => 'Permisos actualizados correctamente.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Action Labels
    |--------------------------------------------------------------------------
    |
    | Translations for permission action prefixes. These are combined with
    | subjects to create human-readable permission labels.
    |
    */

    'actions' => [
        'viewAny' => 'Acceder',
        'view' => 'Ver',
        'create' => 'Crear',
        'update' => 'Editar',
        'delete' => 'Eliminar',
        'deleteAny' => 'Eliminar Varios',
        'restore' => 'Restaurar',
        'restoreAny' => 'Restaurar Varios',
        'forceDelete' => 'Purgar',
        'forceDeleteAny' => 'Purgar Varios',
        'replicate' => 'Duplicar',
        'reorder' => 'Reordenar',
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Permission Labels
    |--------------------------------------------------------------------------
    |
    | Translations for custom permissions defined in config.
    | Keys should match the exact permission name as stored in the database.
    |
    |   'super-admin' => 'Super Administrador',
    |   'Export:Reports' => 'Exportar Reportes',
    |
    */

    'custom' => [
        // 'impersonate-user' => 'Suplantar Usuario',
    ],

    /*
    |--------------------------------------------------------------------------
    | Super Admin
    |--------------------------------------------------------------------------
    */

    'super_admin' => [
        'label' => 'Super Admin',
        'cannot_edit' => 'El rol de super-admin no puede ser modificado.',
        'cannot_delete' => 'El rol de super-admin no puede ser eliminado.',
        'full_access' => 'Este rol puede acceder a todo en el sistema.',
    ],

];
