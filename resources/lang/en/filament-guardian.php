<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Role Resource
    |--------------------------------------------------------------------------
    */

    'roles' => [
        'label' => 'Role',
        'plural' => 'Roles',

        'attributes' => [
            'name' => 'Name',
            'users' => 'Users',
            'permissions' => 'Permissions',
            'created_at' => 'Created',
            'updated_at' => 'Updated',
        ],

        'sections' => [
            'role' => 'Role Details',
            'permissions' => 'Permissions',
        ],

        'tabs' => [
            'resources' => 'Resources',
            'pages' => 'Pages',
            'widgets' => 'Widgets',
            'custom' => 'Custom',
        ],

        'search' => [
            'label' => 'Search',
            'placeholder' => 'Search resources...',
        ],

        'actions' => [
            'select_all' => 'Select All',
        ],

        'messages' => [
            'select_all' => 'Select All',
            'no_permissions' => 'No permissions assigned',
            'permissions_count' => ':count permission assigned|:count permissions assigned',
        ],

        'relations' => [
            'users' => [
                'title' => 'Users',
                'name' => 'Name',
                'email' => 'Email',
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
            'action_label' => 'Permissions',
            'modal_heading' => 'Manage Permissions',
            'role_permissions_title' => 'Role Permissions',
            'role_permissions_message' => 'This user already has :count permission from their assigned roles. Only permissions that can be assigned directly are shown below.|This user already has :count permissions from their assigned roles. Only permissions that can be assigned directly are shown below.',
            'no_additional_permissions' => 'All available permissions are already granted through the user\'s roles.',
            'updated' => 'Permissions updated successfully.',
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
        'viewAny' => 'View Any',
        'view' => 'View',
        'create' => 'Create',
        'update' => 'Update',
        'delete' => 'Delete',
        'deleteAny' => 'Delete Any',
        'restore' => 'Restore',
        'restoreAny' => 'Restore Any',
        'forceDelete' => 'Force Delete',
        'forceDeleteAny' => 'Force Delete Any',
        'replicate' => 'Replicate',
        'reorder' => 'Reorder',
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Permission Labels
    |--------------------------------------------------------------------------
    |
    | Translations for custom permissions defined in config.
    | Keys should match the exact permission name as stored in the database.
    |
    |   'super-admin' => 'Super Administrator',
    |   'Export:Reports' => 'Export Reports',
    |
    */

    'custom' => [
        // Override config labels for multi-language support:
        // 'impersonate-user' => 'Impersonate User',
    ],

    /*
    |--------------------------------------------------------------------------
    | Super Admin
    |--------------------------------------------------------------------------
    */

    'super_admin' => [
        'label' => 'Super Admin',
        'cannot_edit' => 'The super-admin role cannot be modified.',
        'cannot_delete' => 'The super-admin role cannot be deleted.',
        'full_access' => 'This role can access everything in the system.',
    ],

];
