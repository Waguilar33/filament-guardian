# Filament Guardian

Role and permission management for Filament panels using [Spatie Laravel Permission](https://spatie.be/docs/laravel-permission).

## Requirements

- PHP 8.2+
- Laravel 11+
- Filament 4+
- Spatie Laravel Permission 6+

## Installation

```bash
composer require waguilar/filament-guardian
```

## Spatie Setup

If you haven't already configured Spatie Laravel Permission:

```bash
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan migrate
```

Add the trait to your User model:

```php
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles;
}
```

## Basic Usage

Register the plugin in your panel provider:

```php
use Waguilar\FilamentGuardian\FilamentGuardianPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            FilamentGuardianPlugin::make(),
        ]);
}
```

This registers a RoleResource in your panel for managing roles and permissions.

## Guard Configuration

The plugin uses Filament's built-in `authGuard()` configuration to isolate roles between panels:

```php
public function panel(Panel $panel): Panel
{
    return $panel
        ->authGuard('admin')  // Filament's auth guard
        ->plugins([
            FilamentGuardianPlugin::make(),
        ]);
}
```

Each panel can use a different guard to isolate roles and permissions.

## Custom Role/Permission Models

If using custom models (e.g., for UUIDs), configure them in Spatie:

```php
// config/permission.php
return [
    'models' => [
        'role' => App\Models\Role::class,
        'permission' => App\Models\Permission::class,
    ],
];
```

The plugin reads model classes from Spatie's PermissionRegistrar.

## Multi-Tenancy

For panels with tenancy, roles are automatically scoped to the current tenant.

### 1. Enable teams in Spatie config

This must be done before running the Spatie migration:

```php
// config/permission.php
return [
    'teams' => true,
    'column_names' => [
        'team_foreign_key' => 'tenant_id', // Match your tenant column
    ],
];
```

### 2. Modify the Spatie migration for multi-panel support

If you have **both** tenant and non-tenant panels, modify Spatie's published migration to make `tenant_id` nullable:

```php
// model_has_permissions table
if ($teams) {
    $table->uuid($columnNames['team_foreign_key'])->nullable();
    $table->index($columnNames['team_foreign_key'], 'model_has_permissions_team_foreign_key_index');

    $table->primary(
        [$pivotPermission, $columnNames['model_morph_key'], 'model_type'],
        'model_has_permissions_permission_model_type_primary'
    );

    $table->unique(
        [$columnNames['team_foreign_key'], $pivotPermission, $columnNames['model_morph_key'], 'model_type'],
        'model_has_permissions_team_unique'
    );
}

// model_has_roles table - same pattern
if ($teams) {
    $table->uuid($columnNames['team_foreign_key'])->nullable();
    $table->index($columnNames['team_foreign_key'], 'model_has_roles_team_foreign_key_index');

    $table->primary(
        [$pivotRole, $columnNames['model_morph_key'], 'model_type'],
        'model_has_roles_role_model_type_primary'
    );

    $table->unique(
        [$columnNames['team_foreign_key'], $pivotRole, $columnNames['model_morph_key'], 'model_type'],
        'model_has_roles_team_unique'
    );
}
```

### 3. Configure panel with tenancy

```php
use App\Models\Tenant;

public function panel(Panel $panel): Panel
{
    return $panel
        ->authGuard('app')
        ->tenant(Tenant::class)
        ->plugins([
            FilamentGuardianPlugin::make(),
        ]);
}
```

### Role/Permission Scoping

| Panel Config    | Scope                                           |
|-----------------|-------------------------------------------------|
| With tenancy    | `guard_name = X AND tenant_id = current_tenant` |
| Without tenancy | `guard_name = X AND tenant_id IS NULL`          |

## Super Admin

Users with the super-admin role bypass all permission checks via Laravel's Gate.

### Configuration

```php
// config/filament-guardian.php
'super_admin' => [
    'enabled' => true,
    'role_name' => 'Super Admin',
    'intercept' => 'before', // 'before' or 'after'
],
```

| Option | Description |
|--------|-------------|
| `enabled` | Enable/disable super-admin feature globally |
| `role_name` | Name of the super-admin role |
| `intercept` | `before` bypasses ALL gates; `after` only grants if no explicit denial |

### Per-Panel Configuration

```php
FilamentGuardianPlugin::make()
    ->superAdmin(false) // Disable for this panel
```

### Auto-Created Roles for Tenant Panels

For panels with tenancy, the super-admin role is **automatically created** when a tenant is created.

### Manual Setup for Non-Tenant Panels

```bash
# Create the super-admin role for a panel
php artisan guardian:super-admin --panel=admin

# Create role and assign to a user
php artisan guardian:super-admin --panel=admin --email=admin@example.com
```

### Assigning Super Admin in Code

```php
use Waguilar\FilamentGuardian\Facades\Guardian;

// For non-tenant panels
Guardian::createSuperAdminRoleForPanel('admin');
Guardian::assignSuperAdminTo($user, 'admin');

// For tenant panels (set team context first)
setPermissionsTeamId($tenant->getKey());
$user->assignRole(Guardian::getSuperAdminRoleName());
```

### Protection

The super-admin role is protected from modification:
- Cannot be edited or deleted (throws `SuperAdminProtectedException`)
- Edit/delete actions are hidden in the RoleResource

### Checking Super Admin Status

```php
use Waguilar\FilamentGuardian\Facades\Guardian;

Guardian::userIsSuperAdmin($user);
Guardian::isSuperAdminRole($role);
```

## Multi-Panel Setup

Different panels can have different configurations. We recommend using different guards when panels have significantly different configurations, such as one with tenancy and one without. This ensures proper role isolation between panels.

```php
// AdminPanelProvider.php - No tenancy, manages global roles
return $panel
    ->authGuard('admin')
    ->plugins([
        FilamentGuardianPlugin::make(),
    ]);

// AppPanelProvider.php - With tenancy, roles scoped per tenant
return $panel
    ->authGuard('app')
    ->tenant(Tenant::class)
    ->plugins([
        FilamentGuardianPlugin::make(),
    ]);
```

## Configuration Priority Order

All configurable values follow this priority:

1. **Fluent API** - Per-panel in panel provider
2. **Config file** - Global defaults
3. **Translation file** - Fallback labels
4. **Hardcoded default** - Package defaults

## Commands

### guardian:sync

Sync permissions to the database for all panels:

```bash
php artisan guardian:sync

# Sync specific panels only
php artisan guardian:sync --panel=admin --panel=app

# Verbose output
php artisan guardian:sync -v
```

Creates permissions for resources, pages, widgets, and custom permissions.

### guardian:policies

Generate Laravel policies for Filament resources:

```bash
# Interactive mode
php artisan guardian:policies

# Generate for all resources in a panel
php artisan guardian:policies --panel=admin --all-resources

# Generate for all panels
php artisan guardian:policies --all-panels

# Regenerate existing policies
php artisan guardian:policies --panel=admin --all-resources --force
```

### guardian:super-admin

Create the super-admin role for non-tenant panels:

```bash
php artisan guardian:super-admin --panel=admin
php artisan guardian:super-admin --panel=admin --email=admin@example.com
```

## Publishing

### Config

```bash
php artisan vendor:publish --tag="filament-guardian-config"
```

### Translations

```bash
php artisan vendor:publish --tag="filament-guardian-translations"
```

## Role Resource UI

The `RoleResource` provides a tabbed interface for managing permissions.

### Navigation

```php
FilamentGuardianPlugin::make()
    ->navigationLabel('Roles')
    ->navigationIcon('heroicon-o-shield-check')
    ->activeNavigationIcon('heroicon-s-shield-check')
    ->navigationGroup('Settings')
    ->navigationSort(10)
    ->navigationBadge(fn () => Role::count())
    ->navigationBadgeColor('success')
    ->navigationParentItem('settings')
    ->registerNavigation(true)
```

### Resource Labels & Slug

```php
FilamentGuardianPlugin::make()
    ->modelLabel('Role')
    ->pluralModelLabel('Roles')
    ->slug('access-roles')  // URL: /admin/access-roles
```

### Section Configuration

```php
FilamentGuardianPlugin::make()
    ->roleSectionLabel('Role Information')
    ->roleSectionDescription('Configure basic role settings')
    ->roleSectionIcon(Heroicon::OutlinedIdentification)
    ->roleSectionAside()
    ->permissionsSectionLabel('Access Control')
    ->permissionsSectionDescription('Select which actions this role can perform')
    ->permissionsSectionIcon(Heroicon::OutlinedLockClosed)
```

Pass `false` to remove an icon:

```php
FilamentGuardianPlugin::make()
    ->roleSectionIcon(false)
```

All methods accept closures for dynamic values.

### Tab Configuration

```php
FilamentGuardianPlugin::make()
    ->showResourcesTab()           // default: true
    ->showPagesTab()               // default: true
    ->showWidgetsTab()             // default: true
    ->showCustomPermissionsTab()   // default: true
    // Or hide specific tabs
    ->hidePagesTab()
    ->hideWidgetsTab()
```

### Tab Icons

```php
use Filament\Support\Icons\Heroicon;

FilamentGuardianPlugin::make()
    ->resourcesTabIcon(Heroicon::OutlinedRectangleStack)
    ->pagesTabIcon(Heroicon::OutlinedDocument)
    ->widgetsTabIcon(Heroicon::OutlinedPresentationChartBar)
    ->customTabIcon(Heroicon::OutlinedWrench)
```

Default icons:
- Resources: `Heroicon::OutlinedSquare3Stack3d`
- Pages: `Heroicon::OutlinedDocumentText`
- Widgets: `Heroicon::OutlinedChartBar`
- Custom: `Heroicon::OutlinedCog6Tooth`

### Checkbox Layout

```php
use Filament\Support\Enums\GridDirection;

FilamentGuardianPlugin::make()
    // Global defaults for all tabs
    ->permissionCheckboxColumns(3)                        // default: 4
    ->permissionCheckboxGridDirection(GridDirection::Row) // default: Column
    // Override per tab
    ->resourceCheckboxColumns(3)
    ->resourceCheckboxGridDirection(GridDirection::Column)
    ->pageCheckboxColumns(2)
    ->pageCheckboxGridDirection(GridDirection::Row)
    ->widgetCheckboxColumns(2)
    ->widgetCheckboxGridDirection(GridDirection::Row)
    ->customCheckboxColumns(1)
    ->customCheckboxGridDirection(GridDirection::Row)
```

Supports responsive arrays:

```php
FilamentGuardianPlugin::make()
    ->permissionCheckboxColumns([
        'sm' => 2,
        'md' => 3,
        'lg' => 4,
    ])
```

### Resource Sections

```php
FilamentGuardianPlugin::make()
    ->collapseResourceSections()       // Start collapsed
    ->resourceSectionColumns(2)        // Grid layout
    ->showResourceSectionIcon()        // Show navigation icon
```

### Search & Permission Icons

```php
FilamentGuardianPlugin::make()
    ->searchIcon(Heroicon::OutlinedMagnifyingGlass)
    ->permissionAssignedIcon(Heroicon::OutlinedCheckCircle)
```

Pass `false` to hide the icon.

### Select All Toggle

The permissions form includes a "Select All" toggle to quickly select or deselect all permissions.

```php
FilamentGuardianPlugin::make()
    ->selectAllOnIcon(Heroicon::OutlinedCheckCircle)   // default
    ->selectAllOffIcon(Heroicon::OutlinedXCircle)      // default
```

Or via config:

```php
// config/filament-guardian.php
'role_resource' => [
    'select_all_toggle' => [
        'on_icon' => 'heroicon-o-check',
        'off_icon' => 'heroicon-o-x-mark',
    ],
],
```

Pass `false` to hide the icons.

### Permission Labels

| Type      | Label Source                                   |
|-----------|------------------------------------------------|
| Resources | `Resource::getPluralModelLabel()`              |
| Pages     | `Page::getNavigationLabel()`                   |
| Widgets   | `Widget::getHeading()` or humanized class name |
| Custom    | Translation file or permission key             |

## Users Relation Manager

The Role resource includes a Users relation manager out of the box, allowing you to attach/detach users directly from a role.

### Features

- Displays users assigned to the role with name and email columns
- Attach action filters out users who already have the super-admin role
- Supports bulk attach and detach operations

### Customization

When you publish the Role resource, a `UsersRelationManager` stub is included. You can customize it by:

**Custom table configuration:**

```php
// App\Filament\Resources\Roles\Tables\UsersTable.php
use Waguilar\FilamentGuardian\Base\Roles\Tables\BaseUsersTable;

class UsersTable extends BaseUsersTable
{
    public static function configure(Table $table): Table
    {
        return parent::configure($table)
            ->modifyQueryUsing(fn ($query) => $query->where('active', true));
    }
}
```

**Custom relation manager:**

```php
// App\Filament\Resources\Roles\RelationManagers\UsersRelationManager.php
use Waguilar\FilamentGuardian\Base\Roles\RelationManagers\BaseUsersRelationManager;

class UsersRelationManager extends BaseUsersRelationManager
{
    protected static BackedEnum|string|null $icon = 'heroicon-o-user-group';

    public function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return 'Team Members';
    }
}
```

## User Direct Permissions

The package provides a table action for managing user-specific permissions directly, separate from role-based permissions.

### Adding the Action

```php
use Waguilar\FilamentGuardian\Actions\ManageUserPermissionsAction;

public function table(Table $table): Table
{
    return $table
        ->columns([...])
        ->recordActions([
            ViewAction::make(),
            ManageUserPermissionsAction::make(),
        ]);
}
```

### Behavior

The action opens a slide-over modal showing only permissions that can be assigned directly to the user:

- **Role permissions are excluded** - Permissions inherited from roles are not shown (they're managed via roles)
- **Warning alert** - Shows how many permissions the user has from roles
- **Super-admin users** - The action is hidden entirely for super-admin users
- **Automatic cleanup** - When saved, direct permissions that are now also granted via roles are automatically removed

### Customization

The action uses standard Filament action methods:

```php
ManageUserPermissionsAction::make()
    ->label('Custom Label')
    ->icon('heroicon-o-key')
    ->color('primary')
```

## Custom Permissions

Define permissions that don't map to resources, pages, or widgets:

```php
// config/filament-guardian.php
'custom_permissions' => [
    'impersonate-user' => 'Impersonate User',
    'export-orders' => 'Export Orders',
    'manage-settings' => 'Manage Settings',
],
```

The key is the permission name stored in the database, the value is the display label. For multi-language support, add translations under the `custom` key in the lang file.

## Permission Key Format

```php
// config/filament-guardian.php
'permission_key' => [
    'separator' => ':',
    'case' => 'pascal',
],
```

| Case          | Example         |
|---------------|-----------------|
| `pascal`      | `ViewAny:User`  |
| `camel`       | `viewAny:user`  |
| `snake`       | `view_any:user` |
| `kebab`       | `view-any:user` |

## Policy Configuration

```php
'policies' => [
    'path' => app_path('Policies'),
    'merge' => true,
    'methods' => [
        'viewAny', 'view', 'create', 'update', 'delete',
        'restore', 'forceDelete', 'deleteAny', 'restoreAny',
        'forceDeleteAny', 'replicate', 'reorder',
    ],
    'single_parameter_methods' => [
        'viewAny', 'create', 'deleteAny', 'restoreAny',
        'forceDeleteAny', 'reorder',
    ],
],
```

### Per-Resource Configuration

```php
'resources' => [
    'subject' => 'model',
    'manage' => [
        App\Filament\Resources\Blog\CategoryResource::class => [
            'subject' => 'BlogCategory',
        ],
        App\Filament\Resources\RoleResource::class => [
            'methods' => ['viewAny', 'view', 'create'],
        ],
    ],
    'exclude' => [
        App\Filament\Resources\SettingsResource::class,
    ],
],
```

## Publishing RoleResource

```bash
php artisan filament-guardian:publish-role-resource {panel?}
```

Published resources extend base classes from the package. You only override what you actually need - the base classes handle all the standard logic.

### Available Base Classes

| Base Class | Purpose |
|------------|---------|
| `BaseRoleResource` | Resource definition, navigation, model binding |
| `BaseListRoles` | List page with create action |
| `BaseCreateRole` | Create page with permission sync |
| `BaseEditRole` | Edit page with permission hydration and sync |
| `BaseViewRole` | View page with header actions |
| `BaseRoleForm` | Form schema with tabbed permissions |
| `BaseRoleInfolist` | Infolist schema for view page |
| `BaseRolesTable` | Table columns and record actions |

### Example: Custom Table Actions

```php
namespace App\Filament\Admin\Resources\Roles\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Table;
use Waguilar\FilamentGuardian\Base\Roles\Tables\BaseRolesTable;

class RolesTable extends BaseRolesTable
{
    public static function configure(Table $table): Table
    {
        return parent::configure($table)
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
```

### Example: Custom View Page

```php
namespace App\Filament\Admin\Resources\Roles\Pages;

use App\Filament\Admin\Resources\Roles\RoleResource;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Waguilar\FilamentGuardian\Base\Roles\Pages\BaseViewRole;

class ViewRole extends BaseViewRole
{
    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                EditAction::make(),
                DeleteAction::make(),
            ]),
        ];
    }
}
```

### Using the Facade

```php
use Waguilar\FilamentGuardian\Facades\Guardian;

TextInput::make('name')
    ->required()
    ->unique(
        ignoreRecord: true,
        modifyRuleUsing: Guardian::uniqueRoleValidation(),
    )
```

## Translations

Filament Guardian ships with English and Spanish translations. To customize labels, publish the translation files:

```bash
php artisan vendor:publish --tag=filament-guardian-translations
```

This publishes to `lang/vendor/filament-guardian/{locale}/filament-guardian.php`.

The translation file includes:

- `roles.*` - Role resource labels, sections, tabs, and messages
- `users.permissions.*` - User direct permissions modal labels
- `actions.*` - Permission action labels (viewAny, create, update, etc.)
- `custom.*` - Custom permission label overrides
- `super_admin.*` - Super admin role messages

## Testing

```bash
composer test
composer analyse
composer lint
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Waguilar](https://github.com/Waguilar33)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
