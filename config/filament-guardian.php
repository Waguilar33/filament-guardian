<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Super Admin
    |--------------------------------------------------------------------------
    |
    | When enabled, super-admin users bypass all permission checks via
    | Gate. The role is auto-created for tenant panels when a
    | tenant is created, or manually via guardian:super-admin command
    | for non-tenant panels.
    |
    | Intercept modes:
    | - 'before': Super-admin bypasses ALL gates (recommended)
    | - 'after': Super-admin only grants if no explicit denial
    |
    */

    'super_admin' => [
        'enabled' => true,
        'role_name' => 'Super Admin',
        'intercept' => 'before',
    ],

    /*
    |--------------------------------------------------------------------------
    | Permission Key Builder
    |--------------------------------------------------------------------------
    |
    | Configure how permission keys are generated. The separator joins the
    | action and subject, while the case determines the formatting.
    |
    | Supported cases: snake, kebab, pascal, camel, upper_snake, lower_snake
    |
    */

    'permission_key' => [
        'builder' => Waguilar\FilamentGuardian\Support\PermissionKeyBuilder::class,
        'separator' => ':',
        'case' => 'pascal',
    ],

    /*
    |--------------------------------------------------------------------------
    | Policies
    |--------------------------------------------------------------------------
    |
    | Configure policy generation for Filament resources. The generator can
    | create Laravel policies with permission checks for each action.
    |
    | The "merge" option determines whether resource-specific methods are
    | combined with defaults (true) or replace them entirely (false).
    |
    */

    'policies' => [
        'path' => app_path('Policies'),
        'merge' => true,
        'methods' => [
            'viewAny',
            'view',
            'create',
            'update',
            'delete',
            'restore',
            'forceDelete',
            'deleteAny',
            'restoreAny',
            'forceDeleteAny',
            'replicate',
            'reorder',
        ],
        'single_parameter_methods' => [
            'viewAny',
            'create',
            'deleteAny',
            'restoreAny',
            'forceDeleteAny',
            'reorder',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resources
    |--------------------------------------------------------------------------
    |
    | Configure permission generation for Filament resources. The "subject"
    | determines what name is used in permission keys (model name vs class).
    |
    | Use "manage" to override policy methods for specific resources.
    | Use "exclude" to skip resources during permission/policy generation.
    |
    */

    'resources' => [
        'subject' => 'model', // 'model' or 'class'
        'manage' => [],
        'exclude' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pages
    |--------------------------------------------------------------------------
    |
    | Configure permission generation for Filament pages. Pages typically
    | only need a single permission (usually "view").
    |
    */

    'pages' => [
        'subject' => 'class',
        'prefix' => 'view',
        'exclude' => [
            Filament\Pages\Dashboard::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Widgets
    |--------------------------------------------------------------------------
    |
    | Configure permission generation for Filament widgets. Like pages,
    | widgets typically only need view permissions.
    |
    */

    'widgets' => [
        'subject' => 'class',
        'prefix' => 'view',
        'exclude' => [
            Filament\Widgets\AccountWidget::class,
            Filament\Widgets\FilamentInfoWidget::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Permissions
    |--------------------------------------------------------------------------
    |
    | Permissions that don't map to resources, pages, or widgets.
    | Define as 'permission-key' => 'Label' pairs.
    |
    | The label is used as the default display text. For multi-language
    | support, add translations to the lang file under 'custom' key
    | which will override the label defined here.
    |
    */

    'custom_permissions' => [
        // 'impersonate-user' => 'Impersonate User',
        // 'export-orders' => 'Export Orders',
    ],

    /*
    |--------------------------------------------------------------------------
    | Role Resource UI
    |--------------------------------------------------------------------------
    |
    | Configure the appearance and behavior of the Role resource form.
    | These settings provide defaults that can be overridden via the
    | fluent API on FilamentGuardianPlugin::make().
    |
    | Fluent API always takes precedence over config values.
    |
    */

    'role_resource' => [

        /*
        |--------------------------------------------------------------------------
        | Resource Labels & Slug
        |--------------------------------------------------------------------------
        |
        | Customize the model label, plural label, and URL slug for the Role
        | resource. Set to null to use translation defaults.
        |
        */

        'model_label' => null,
        'plural_model_label' => null,
        'slug' => null,

        /*
        |--------------------------------------------------------------------------
        | Navigation
        |--------------------------------------------------------------------------
        |
        | Customize how the Role resource appears in the navigation.
        | Set to null to use Filament defaults.
        |
        */

        'navigation' => [
            'cluster' => null,      // e.g., \App\Filament\Clusters\Settings::class
            'icon' => null,         // e.g., 'heroicon-o-shield-check' or Heroicon::OutlinedShieldCheck
            'active_icon' => null,  // Icon shown when navigation item is active
            'label' => null,        // Navigation label (defaults to plural model label)
            'group' => null,        // Navigation group
            'sort' => null,         // Sort order (integer)
            'badge' => null,        // Badge text (use closures in fluent API for dynamic values)
            'badge_color' => null,  // Badge color
            'parent_item' => null,  // Parent navigation item for sub-navigation
            'register' => true,     // Whether to show in navigation
        ],

        /*
        |--------------------------------------------------------------------------
        | Section Configuration
        |--------------------------------------------------------------------------
        |
        | Customize the appearance of sections in the role form.
        | Set label/description to null to use translation defaults.
        | Icon accepts string ('heroicon-o-shield-check') or false to hide.
        |
        */

        'sections' => [
            'role' => [
                'label' => null,       // null = use translation
                'description' => null, // null = no description
                'icon' => null,        // default: Heroicon::OutlinedShieldCheck
                'aside' => false,
            ],
            'permissions' => [
                'label' => null,
                'description' => null,
                'icon' => null,        // default: Heroicon::OutlinedKey
                'aside' => false,
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | Tab Configuration
        |--------------------------------------------------------------------------
        |
        | Configure each permission tab in the role form. Each tab supports:
        | - visible: Whether the tab is shown (default: true)
        | - icon: Tab icon (null = default, false = no icon)
        | - checkbox_columns: Columns for checkboxes (null = use default)
        | - checkbox_grid_direction: 'column' or 'row' (null = use default)
        |
        */

        'tabs' => [
            'default' => [
                'checkbox_columns' => 4,
                'checkbox_grid_direction' => 'column',
            ],
            'resources' => [
                'visible' => true,
                'icon' => null,              // default: Heroicon::OutlinedSquare3Stack3d
                'checkbox_columns' => null,  // null = use default
                'checkbox_grid_direction' => null,
            ],
            'pages' => [
                'visible' => true,
                'icon' => null,              // default: Heroicon::OutlinedDocumentText
                'checkbox_columns' => null,
                'checkbox_grid_direction' => null,
            ],
            'widgets' => [
                'visible' => true,
                'icon' => null,              // default: Heroicon::OutlinedChartBar
                'checkbox_columns' => null,
                'checkbox_grid_direction' => null,
            ],
            'custom' => [
                'visible' => true,
                'icon' => null,              // default: Heroicon::OutlinedCog6Tooth
                'checkbox_columns' => null,
                'checkbox_grid_direction' => null,
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | Search Icon
        |--------------------------------------------------------------------------
        |
        | Icon for the resource search input field.
        | Set to null to use default, or false to hide the icon.
        |
        */

        'search_icon' => null, // default: Heroicon::OutlinedMagnifyingGlass

        /*
        |--------------------------------------------------------------------------
        | Permission Assigned Icon
        |--------------------------------------------------------------------------
        |
        | Icon shown next to assigned permissions in the view infolist.
        | Set to null to use default, or false to hide the icon.
        |
        */

        'permission_assigned_icon' => null, // default: Heroicon::OutlinedCheckCircle

        /*
        |--------------------------------------------------------------------------
        | Select All Toggle
        |--------------------------------------------------------------------------
        |
        | Configure the icons for the "Select All" toggle in the permissions form.
        | Set to null to use defaults, or false to hide the icon.
        |
        */

        'select_all_toggle' => [
            'on_icon' => null,  // default: Heroicon::OutlinedCheckCircle
            'off_icon' => null, // default: Heroicon::OutlinedXCircle
        ],

        /*
        |--------------------------------------------------------------------------
        | Resource Sections
        |--------------------------------------------------------------------------
        |
        | Configure the resource sections layout within the Resources tab.
        |
        */

        'resource_sections' => [
            'columns' => 1,       // Grid columns for resource sections
            'collapsed' => false, // Whether sections start collapsed
            'icon' => false,      // Show resource navigation icon in sections
        ],

    ],
];
