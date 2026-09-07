# Filament Guardian

<img src="art/banner.png" alt="Filament Guardian" class="filament-hidden">

[![Latest Version on Packagist](https://img.shields.io/packagist/v/waguilar33/filament-guardian.svg?style=flat-square&label=version)](https://packagist.org/packages/waguilar33/filament-guardian)
[![Stars](https://img.shields.io/github/stars/Waguilar33/filament-guardian.svg?style=flat-square&label=stars)](https://github.com/Waguilar33/filament-guardian/stargazers)
[![Total Downloads](https://img.shields.io/packagist/dt/waguilar33/filament-guardian.svg?style=flat-square&label=downloads)](https://packagist.org/packages/waguilar33/filament-guardian)
[![License](https://img.shields.io/github/license/Waguilar33/filament-guardian.svg?style=flat-square&label=license)](LICENSE.md)
[![PHPStan](https://github.com/Waguilar33/filament-guardian/actions/workflows/phpstan.yml/badge.svg)](https://github.com/Waguilar33/filament-guardian/actions/workflows/phpstan.yml)

A complete role and permission management plugin for Filament, built on [Spatie Laravel Permission](https://spatie.be/docs/laravel-permission). Drop it into any panel and get a fully-featured RoleResource with a tabbed permission UI out of the box — no boilerplate required.

Roles and permissions are automatically scoped to each panel's auth guard, so multi-panel apps stay isolated without any extra configuration. Multi-tenancy is supported too, with roles scoped per tenant when your panel uses it. A built-in super-admin role bypasses all permission checks, and direct per-user permission overrides let you go beyond what roles alone can express.

Works with **Filament v4 and v5**.

## Requirements

- PHP 8.2+
- Laravel 11+
- Filament 4+
- Spatie Laravel Permission 6 or 7

## Installation

```bash
composer require waguilar33/filament-guardian
```

## Spatie Setup

If you haven't already configured Spatie Laravel Permission:

```bash
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
```

> **Important:** If you plan to use multi-tenancy, enable teams in `config/permission.php` **before** running the migration — this setting affects the database schema and cannot be changed after the fact.
>
> ```php
> // config/permission.php
> 'teams' => true,
> 'column_names' => [
>     'team_foreign_key' => 'tenant_id', // match your tenant's primary key column
> ],
> ```

```bash
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

Spatie ships with its own `Role` and `Permission` models out of the box — no extra setup needed for most apps. If you need to customize them (for example, to use UUIDs or add extra relationships), create your own models that extend Spatie's and point to them in the config:

```php
// config/permission.php
'models' => [
    'role'       => App\Models\Role::class,
    'permission' => App\Models\Permission::class,
],
```

The plugin reads these from Spatie's registrar automatically — no additional configuration needed. For anything beyond this — custom primary keys, extra columns, or complex relationships — refer to the [Spatie documentation](https://spatie.be/docs/laravel-permission) for the full picture.

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

This is the minimum setup. It registers a `RoleResource` in your panel where you can create roles, assign permissions to them, and attach users. Roles are scoped to whatever auth guard the panel uses — if you don't configure one explicitly, Filament falls back to the application's default guard.

For single-panel apps that's usually fine. For multi-panel apps — or any time you want roles to be isolated per panel — you'll want to set an explicit guard as described in the next section.

## Guard Configuration

This is optional. If you don't configure a guard, Filament defaults to the `web` guard and everything works as expected for single-panel apps.

Guard configuration becomes relevant when you have multiple panels and want their roles to be completely separate. The plugin scopes roles and permissions to whichever guard the panel uses, so two panels with different guards will have independent role sets.

To set an explicit guard, add `authGuard()` to your panel provider:

```php
// AdminPanelProvider.php
public function panel(Panel $panel): Panel
{
    return $panel
        ->authGuard('admin') // <-- roles scoped to this guard
        ->plugins([
            FilamentGuardianPlugin::make(),
        ]);
}
```

```php
// AppPanelProvider.php
public function panel(Panel $panel): Panel
{
    return $panel
        ->authGuard('web') // <-- completely separate set of roles
        ->plugins([
            FilamentGuardianPlugin::make(),
        ]);
}
```

With this setup, roles created in the admin panel are invisible to the app panel and vice versa. If two panels share the same guard, they share the same roles — which is sometimes intentional, but usually not what you want when the panels serve different audiences.

> **Important:** Any custom guard must be registered in `config/auth.php` before it can be used. Laravel will throw an error if you reference a guard that isn't defined there.
>
> ```php
> // config/auth.php
> 'guards' => [
>     'web' => [
>         'driver'   => 'session',
>         'provider' => 'users',
>     ],
>     'admin' => [
>         'driver'   => 'session',
>         'provider' => 'users',
>     ],
> ],
> ```

### Changing a panel's guard later

Roles and permissions carry the guard they were created under, and nothing rewrites them when you change a panel's `authGuard()`. Point a panel at a new guard and its existing roles go invisible, every user loses every permission, and the Roles page comes up empty — silently.

If you already have roles and permissions on the old guard, move them across with [`guardian:migrate-guard`](#2-guardianmigrate-guard) rather than re-creating them by hand:

```bash
php artisan guardian:migrate-guard --from=web --to=admin --dry-run
php artisan guardian:migrate-guard --from=web --to=admin
```

`guardian:sync` warns you when it finds permissions under a guard no panel uses, so you'll normally be told this happened before you go looking for it.

## Multi-Tenancy

Filament has built-in multi-tenancy support that automatically scopes the panel to the current tenant — resource queries, record resolution, and new record associations are all handled by Filament itself. The plugin integrates with this by reading the active tenant from Filament's context and using Spatie's teams feature to scope roles and permissions to that tenant accordingly.

What this means in practice: once you complete the setup below, role management in tenant panels just works — roles created within a tenant are only visible within that tenant.

### 1. Add the tenant relationship to your Role model

The Role model needs a relationship back to your tenant. If you already have a custom Role model from the setup above, add the relationship to it. If you're starting fresh, create one that extends Spatie's:

```php
// app/Models/Role.php
namespace App\Models;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
```

The relationship method name must match your Filament panel's tenant ownership relationship — by default this is the lowercase version of your tenant model name:

| Tenant model | Method name |
|---|---|
| `Tenant` | `tenant()` |
| `Team` | `team()` |
| `Organization` | `organization()` |

If you need a custom name, configure it in your panel: `->tenantOwnershipRelationshipName('workspace')`.

If you created a new model, register it in Spatie's config:

```php
// config/permission.php
return [
    'models' => [
        'role' => App\Models\Role::class,
    ],
];
```

### 2. Allow null tenant on Spatie's pivot tables

This step is only required when you have **both tenant and non-tenant panels** in a single application.

Since Laravel runs all panels against the same database, non-tenant panels need to store roles with no tenant (`tenant_id = NULL`). But when Spatie's teams feature is enabled, the `team_foreign_key` column on the `model_has_permissions` and `model_has_roles` pivot tables is created as `NOT NULL` — which means inserting a role with no tenant fails.

There's also a primary key concern: both pivot tables include `team_foreign_key` in their composite primary key by default. Keeping a nullable column in a primary key causes inconsistent behavior across databases (MySQL treats two NULL values as distinct, others don't). The fix is to remove `team_foreign_key` from the primary key and use a unique constraint instead, which handles both the tenant and non-tenant cases cleanly.

This is a key difference from other role management plugins that only support a single panel configuration.

Publish and run the migration included with this package:

```bash
php artisan vendor:publish --tag="filament-guardian-multitenancy"
php artisan migrate
```

> **Note:** The published migration uses `unsignedBigInteger` by default, matching Spatie's default integer IDs. If your application uses UUID primary keys, open the published migration and replace `->unsignedBigInteger()` with `->uuid()` before running it.

> **Important:** If you already have data in these tables, the migration is safe to run as long as your existing records don't violate the new unique constraint. On large production tables, consider running it during a maintenance window.

> **Note:** This migration cannot run on SQLite, which does not support dropping a primary key. Use MySQL, MariaDB, or PostgreSQL for a mixed tenant/non-tenant setup.

#### Already on an earlier version? Run the corrective migration

Earlier releases left `model_has_permissions` with a primary key of `(permission_id, model_id, model_type)` — the tenant column removed. Permissions are **not** tenant-scoped in Spatie: one permission row is shared by every tenant. That key therefore forbids a user who belongs to two tenants from holding the same permission in both, and the second grant fails with a duplicate key error:

```
SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry
```

If you published the multi-tenancy migration before this release, publish and run the corrective one:

```bash
php artisan vendor:publish --tag="filament-guardian-multitenancy-fix"
php artisan migrate
```

It drops that primary key and nothing else. The unique constraint on `(tenant_id, permission_id, model_id, model_type)` still prevents granting the same permission twice within one tenant. It is safe to run more than once — it checks for the key first — and it leaves `model_has_roles` untouched, since roles are tenant-scoped and each tenant gets its own role row.

Fresh installs don't need this: the migration published by `filament-guardian-multitenancy` already produces the corrected shape.

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

### 4. How role scoping works

Once everything is set up, the plugin automatically filters roles and permissions based on two things: the panel's auth guard and the current tenant context.

When a user opens the Roles section in a panel, the plugin queries only the roles that belong to that panel's guard and tenant combination. This means:

- A role created in the admin panel (no tenancy, `guard = admin`) is invisible to the app panel and to other tenants
- A role created in the app panel for Tenant A is invisible to Tenant B, even though they share the same database and the same guard

Internally, this translates to:

| Panel | Query scope |
|---|---|
| With tenancy | `guard_name = 'app' AND tenant_id = <current tenant>` |
| Without tenancy | `guard_name = 'admin' AND tenant_id IS NULL` |

The `tenant_id IS NULL` condition is exactly why step 2 is necessary — without making that column nullable, non-tenant panel roles can't be stored at all.

## Filtering Users in Non-Tenant Panels

This only applies when your application has **both tenant and non-tenant panels** running side by side.

In tenant panels, Filament scopes the Users relation manager automatically — it queries users through the tenant's ownership relationship, so only users belonging to the current tenant are shown. Non-tenant panels have no equivalent mechanism. There's no ownership relationship, no tenant column on users, and no guard column — nothing on the User model signals which panel a user belongs to. The result: the Users relation manager in your non-tenant panel shows every user in the database.

The fix is to add a discriminator to your User model and apply it in two places within your non-tenant panel: the UserResource and the UsersRelationManager.

### 1. Add a discriminator column

Since nothing on the User model inherently ties a user to a specific panel, you need to add something that does. What that looks like depends on your app — a boolean flag, a role string, an email domain check, or anything else that reliably separates your non-tenant panel users from tenant users. The examples below use a simple boolean, but adapt it to whatever fits your data model.

```php
// database/migrations/xxxx_add_is_admin_to_users_table.php
$table->boolean('is_admin')->default(false);
```

### 2. Add a query scope to your User model

Wrap the discriminator in a scope so the filtering logic lives in one place and can be reused across both steps below.

```php
// app/Models/User.php
use Illuminate\Database\Eloquent\Builder;

public function scopeAdmins(Builder $query): Builder
{
    return $query->where('is_admin', true);
}
```

### 3. Apply it in your UserResource

```php
// app/Filament/Admin/Resources/UserResource.php
public static function getEloquentQuery(): Builder
{
    return parent::getEloquentQuery()->admins();
}
```

### 4. Apply it in the UsersRelationManager

The relation manager needs the scope in two places — the table rows and the attach dropdown. Both are required; missing either one leaves a gap.

```php
// app/Filament/Admin/Resources/Roles/RelationManagers/UsersRelationManager.php
use Waguilar\FilamentGuardian\Base\Roles\Tables\BaseUsersTable;

class UsersRelationManager extends BaseUsersRelationManager
{
    public function table(Table $table): Table
    {
        return BaseUsersTable::configure($table)
            ->modifyQueryUsing(fn (Builder $query) => $query->admins())
            ->headerActions([
                AttachAction::make()
                    ->recordSelectOptionsQuery(fn (Builder $query) => $query->admins())
                    ->preloadRecordSelect()
                    ->multiple(),
            ]);
    }
}
```

`modifyQueryUsing` filters the users shown in the table. `recordSelectOptionsQuery` filters the users shown in the attach dropdown.

## Super Admin

The super-admin concept comes from [Spatie Laravel Permission](https://spatie.be/docs/laravel-permission/v7/basic-usage/super-admin). A super-admin is a user who bypasses all permission checks entirely — instead of assigning every possible permission to that user, you designate them as super-admin and Laravel's Gate handles the rest.

This is useful for the first user in a new panel (who needs access to everything before any roles are configured), internal admin accounts, or any user who should never be blocked by permission rules.

> **Important:** The super-admin bypass only works through Laravel's Gate system — meaning `can()`, policies, and `@can()` directives. It does **not** apply to direct Spatie method calls like `->hasPermissionTo()`. If your code calls those directly, super-admin users will still need those permissions assigned explicitly. Use Gate-based checks throughout your application to get the full benefit.

### 1. Global configuration

The plugin ships with super-admin enabled by default. You can adjust the global defaults in the published config:

```php
// config/filament-guardian.php
'super_admin' => [
    'enabled'   => true,
    'role_name' => 'Super Admin',
    'intercept' => 'before',
],
```

| Option | Description |
|---|---|
| `enabled` | Enable or disable the super-admin feature entirely |
| `role_name` | The name of the super-admin role in the database |
| `intercept` | When to intercept the Gate — see below |

#### Intercept modes

The `intercept` option controls when the super-admin bypass runs relative to your normal authorization logic:

**`before` (recommended)** — The Gate is intercepted before any permission check runs. If the user is a super-admin, access is granted immediately, no policy or permission is consulted. This is the right choice for most applications.

**`after`** — The Gate is intercepted after normal authorization runs. Access is only granted if no policy explicitly denied it. Use this when you have specific gates or policies that should block even super-admins — for example, a system-level record that nobody should be able to delete.

### 2. Per-panel configuration

The global config applies to all panels by default. If you need different behaviour on a specific panel — a different role name, a different intercept mode, or super-admin disabled entirely — configure it on the plugin directly:

```php
FilamentGuardianPlugin::make()
    ->superAdmin()                          // enable for this panel (default: from config)
    ->superAdminRoleName('Administrator')   // custom role name for this panel
    ->superAdminIntercept('after')          // intercept mode for this panel
```

Per-panel settings always take priority over the global config. Any option not set on the plugin falls back to the global config value.

### 3. Super-admin is scoped to the panel's guard

The super-admin bypass is granted per guard, the same way roles and permissions are. A user holding `Super Admin` under the `web` guard is a super admin in panels that use the `web` guard — and an ordinary user everywhere else. Give someone blanket access to two panels on different guards and they need the super-admin role under both.

Outside a panel there is no guard to scope to, so the check falls back to matching the super-admin role under **any** guard:

| Context | Bypass applies when the user holds `Super Admin` under… |
|---------|--------------------------------------------------------|
| A Filament panel request | that panel's auth guard |
| Console command, queued job, non-Filament route | any guard |

Guessing a guard outside a panel would mean silently picking one panel's guard in a multi-panel app, so the check stays broad there instead — which is also what keeps super-admin working in your commands and jobs.

> **Upgrading:** before this, the bypass matched the role name under any guard everywhere, so one `Super Admin` role granted access to every panel. If you relied on that with multiple guards, assign the super-admin role under each panel's guard — `php artisan guardian:super-admin --panel=<id>` creates and assigns it per panel.

Role *protection* is deliberately not scoped this way: a super-admin role is blocked from being edited or deleted no matter which panel you're looking at it from.

### 4. Tenant panels — automatic role creation

For panels with tenancy, the super-admin role is created automatically every time a new tenant is created. The plugin listens for Eloquent's `created` event on your tenant model and creates a scoped super-admin role for that tenant in the background — no command to run, no migration to write, nothing to wire up manually.

Once a tenant is created, the plugin automatically creates a scoped super-admin role for it, ready to be assigned to whoever should have full access to that tenant.

### 5. Non-tenant panels — manual role creation

For panels without tenancy, the role is not created automatically because there is no tenant lifecycle event to hook into. Depending on your situation, you have two ways to create the role and assign it.

#### Via CLI — for deployments and first-time setup

Use the provided Artisan command, typically as part of your deployment process:

```bash
# Create the super-admin role for the panel
php artisan guardian:super-admin --panel=admin

# Or create it and immediately assign it to an existing user
php artisan guardian:super-admin --panel=admin --email=admin@example.com
```

If you are setting up a fresh environment from scratch, the full recommended sequence is:

```bash
php artisan migrate
php artisan guardian:sync
php artisan guardian:create-user --name="Admin" --email="admin@example.com" --password="changeme"
php artisan guardian:super-admin --panel=admin --email="admin@example.com"
```

#### Via code — for in-app workflows

If you need to create the role or promote a user to super-admin from within your application — for example, in an onboarding flow, a user management screen, or a seeder — use the `Guardian` facade instead:

```php
use Waguilar\FilamentGuardian\Facades\Guardian;

// Create the super-admin role for a panel (if it doesn't exist yet)
Guardian::createSuperAdminRole('admin');

// Assign the super-admin role to a user
Guardian::assignSuperAdminTo($user, 'admin');

// Or if you need to retrieve the role first
$role = Guardian::getSuperAdminRole('admin');
```

### 6. Facade reference

All methods accept an optional `$panelId`. When omitted, the method resolves configuration from the current Filament panel.

```php
use Waguilar\FilamentGuardian\Facades\Guardian;

Guardian::isSuperAdminEnabled(?string $panelId);    // bool
Guardian::getSuperAdminRoleName(?string $panelId);  // string
Guardian::getSuperAdminIntercept(?string $panelId); // 'before'|'after'
```

For non-tenant panels, use these methods to create, retrieve, and assign the super-admin role.

```php
Guardian::createSuperAdminRole(?string $panelId);      // Role
Guardian::getSuperAdminRole(?string $panelId);         // ?Role
Guardian::assignSuperAdminTo($user, ?string $panelId); // void
```

For tenant panels, `$tenant` and `$guard` are optional and resolve from `Filament::getTenant()` and the current panel when omitted. Pass them explicitly when calling outside a Filament request — console commands, observers, queued jobs.

```php
Guardian::createSuperAdminRoleForTenant(
    ?Model $tenant,
    ?string $guard,
    ?string $panelId
); // Role

Guardian::getSuperAdminRoleForTenant(
    ?Model $tenant,
    ?string $guard,
    ?string $panelId
); // ?Role

Guardian::assignSuperAdminToForTenant(
    Authenticatable $user,
    ?Model $tenant,
    ?string $guard,
    ?string $panelId
); // void
```

To check whether a user or role has super-admin status:

```php
Guardian::userIsSuperAdmin($user); // bool
Guardian::isSuperAdminRole($role); // bool
```

To customise how users are created by the `guardian:create-user` command:

```php
Guardian::createUserUsing(Closure $callback);         // void
Guardian::createUser(string $userModel, array $data); // Model
```

### 6. Protection

The super-admin role is protected at two levels: the Eloquent model and the Filament UI.

**Model-level** — When super-admin is enabled, the plugin registers `updating` and `deleting` observers on your Role model. Any attempt to modify or delete the super-admin role — whether from the UI, a seeder, or application code — throws a `SuperAdminProtectedException`. The `updating` observer fires on any field change, not just renames, so the role cannot be modified in any way once created.

**UI-level** — The edit and delete actions are hidden for the super-admin role in the RoleResource. This is a UX convenience on top of the model-level guard, not a replacement for it.

Both layers respect the `enabled` flag — if you disable super-admin in the config or on the plugin, the protection is lifted and the role behaves like any other.

`SuperAdminProtectedException` extends `RuntimeException`, so you can catch it if you need to handle the error gracefully in application code.

## Permission Key Format

Every permission the package generates and checks follows a consistent `action:subject` format — for example `ViewAny:User` or `Access:Dashboard`. The separator and case are configurable, and the entire key-building algorithm can be swapped out if you need something the config options alone can't express.

```php
// config/filament-guardian.php
'permission_key' => [
    'builder'   => Waguilar\FilamentGuardian\Support\PermissionKeyBuilder::class,
    'separator' => ':',
    'case'      => 'pascal',
],
```

| Case | Example |
|------|---------|
| `pascal` | `ViewAny:User` |
| `camel` | `viewAny:user` |
| `snake` | `view_any:user` |
| `kebab` | `view-any:user` |
| `upper_snake` | `VIEW_ANY:USER` |
| `lower_snake` | `view_any:user` |

### Custom key builder

The `builder` key lets you replace the default key-building logic entirely — useful when separator and case alone aren't enough. For example, if two resources share the same model name but live in different namespaces, you can include the navigation group or namespace in the key to make them distinct.

Implement `Waguilar\FilamentGuardian\Contracts\PermissionKeyBuilder`:

```php
use Waguilar\FilamentGuardian\Contracts\PermissionKeyBuilder as PermissionKeyBuilderContract;

class CustomPermissionKeyBuilder implements PermissionKeyBuilderContract
{
    public function __construct(
        private readonly string $separator = ':',
        private readonly string $case = 'pascal',
    ) {}

    public function build(string $action, string $subject, ?string $entity = null): string
    {
        // your custom key generation logic
        return $this->format($action) . $this->separator . $this->format($subject);
    }

    public function format(string $value): string { /* ... */ }
    public function getSeparator(): string { return $this->separator; }
    public function getCase(): string { return $this->case; }
    public function extractSubject(string $permissionKey): string { /* ... */ }
}
```

The constructor **must** accept `$separator` and `$case` — the service provider passes those from config when instantiating the builder. Register your implementation in the config:

```php
'permission_key' => [
    'builder' => App\Support\CustomPermissionKeyBuilder::class,
],
```

## Custom Permissions

For permissions that don't map to any resource, page, or widget — feature flags, cross-cutting actions, or anything purely app-defined — define them directly in the config. They'll be picked up by `guardian:sync` and appear in the Custom tab of the role form.

```php
// config/filament-guardian.php
'custom_permissions' => [
    'impersonate-user' => 'Impersonate User',
    'export-orders'    => 'Export Orders',
    'manage-settings'  => 'Manage Settings',
],
```

The key is the permission name stored in the database; the value is the display label shown in the UI. For multi-language support, add translations under the `custom` key in the lang file — those override the labels defined here.

## Commands

The package ships with four Artisan commands. Two belong in your deployment pipeline, one is a development-time generator, and one handles initial user and role setup.

### 1. guardian:sync

Scans your Filament resources, pages, widgets, and custom permissions and syncs them to the database. Run this after every deploy — it creates any permissions that don't exist yet and leaves existing ones untouched, so it's safe to run repeatedly.

```bash
php artisan migrate
php artisan guardian:sync
```

```bash
# Sync specific panels only
php artisan guardian:sync --panel=admin --panel=app

# Verbose output to see each permission being created
php artisan guardian:sync -v
```

| Type | Permissions created |
|------|---------------------|
| Resources | `ViewAny:User`, `Create:User`, `Update:User`, `Delete:User`, etc. |
| Pages | `Access:Dashboard`, `Access:Settings`, etc. |
| Widgets | `View:StatsOverview`, `View:RevenueChart`, etc. |
| Custom | Whatever you define in `config/filament-guardian.php` |

Example zero-downtime deployment:

```bash
php artisan down
php artisan migrate --force
php artisan guardian:sync
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan up
```

#### Pruning stale permissions

By default `guardian:sync` only adds — it creates new permissions and leaves existing ones untouched. So when you remove a resource, page, widget, or custom permission, its row lingers in the database and keeps showing up on the role form. Pass `--prune` to delete whatever the sync no longer produces:

```bash
# Delete the permissions this sync no longer produces, across every panel
php artisan guardian:sync --prune

# Verbose, so you can see exactly which permissions get deleted
php artisan guardian:sync --prune -v
```

`--prune` is **destructive and opt-in** — passing the flag is the confirmation, there's no separate prompt. Guardian owns the permission space for the panels it manages, so anything it didn't generate this run is treated as stale and removed:

| A permission is pruned when… | Example |
|------------------------------|---------|
| You delete or rename a resource, page, or widget | `ViewAny:OldResource` |
| You remove a key from `custom_permissions` | `export-orders` |
| You add an entry to one of the `exclude` lists | `View:LegacyReport` |
| You pass `--no-relation-managers` alongside `--prune` | every relation-manager permission |

A pruned permission is detached from every role and user before it's deleted, so you're never left with dangling assignments — no manual cleanup needed.

#### Pruning is scoped to the guard

Permissions are scoped to each panel's auth guard, and `--prune` only touches the guard(s) it actually syncs. If two panels share a guard and you sync only one of them, pruning that guard would delete the other panel's permissions — so Guardian skips it with a warning instead:

```bash
# admin and support both use the "web" guard. Syncing only admin would
# orphan support's permissions, so Guardian skips the web guard (with a warning).
php artisan guardian:sync --panel=admin --prune

# Give the run the full picture for the shared guard, and pruning is safe:
php artisan guardian:sync --prune                       # all panels
php artisan guardian:sync --panel=admin --panel=support --prune
```

#### Detecting an abandoned guard

Every sync ends by checking for permissions that belong to a guard no panel uses. That's what changing a panel's guard looks like from the outside: the old guard's roles and permissions are still in the database, still attached to users, and completely invisible to every panel.

```
WARN  Guard 'web' has 24 permission(s) and 1 role(s) but no panel uses it.
      Users holding them are not authorized anywhere.

INFO  Move them with:
      php artisan guardian:migrate-guard --from=web --to=admin --mode=merge --dry-run
```

### 2. guardian:migrate-guard

Moves roles and permissions from one auth guard to another. Use it when you started on the default `web` guard and later split a panel onto its own guard — the recommended way to adopt per-panel guards after the fact.

```bash
# See what would change, without writing anything
php artisan guardian:migrate-guard --from=web --to=admin --dry-run

# Do it
php artisan guardian:migrate-guard --from=web --to=admin
```

Rows are moved by rewriting `guard_name` in place, so every primary key stays stable and the pivot tables follow automatically. Nobody loses a role and no role loses a permission.

| Option | |
|--------|--|
| `--from=` | The guard to move away from (required) |
| `--to=` | The guard to move to (required) |
| `--mode=` | `move` (default) or `merge` — see below |
| `--only=` | `permissions` or `roles`, to limit the run |
| `--dry-run` | Report the plan and write nothing (never exits non-zero) |
| `--allow-unregistered-guard` | Proceed even if `--to` is missing from `config/auth.php` |
| `--force` | Skip the confirmation prompt |

#### The two modes

They differ only in what happens when a name already exists under the target guard.

**`--mode=move`** (default) aborts and lists the conflicts, changing nothing:

```
ERROR These names already exist under guard 'admin', so nothing was changed.
  permission ViewAny:Post
  role Editor

INFO  Re-run with --mode=merge to fold them into the existing target rows.
```

**`--mode=merge`** keeps the target row, repoints everything that referenced the source row onto it, then deletes the source row. A role that exists under both guards ends up with the **union** of both permission sets, and a user who held both copies ends up holding one.

If you re-run `guardian:sync` *after* changing the panel's guard, the target guard already has a copy of every name — so `merge` is the mode you want. The warning printed at the end of the sync tells you which one applies.

> **Back up your database first, and run it in maintenance mode.** The whole migration runs in a single transaction and `--dry-run` shows you the plan, but this rewrites authorization data, there's no undo, and the transaction holds write locks on the roles and permissions tables for its duration.

The target guard must be registered in `config/auth.php` — migrating onto a guard Laravel doesn't know about leaves an authorization system that throws on `hasPermissionTo()` and `assignRole()`. If you're adding the guard in the same deploy, pass `--allow-unregistered-guard`.

`--only` migrates half the picture, which leaves roles on one guard linked to permissions on another. Spatie throws on a cross-guard link, so run both halves before using the application — the command warns you when you've only done one.

Permissions are migrated before roles, because merging permissions can leave two roles pointing at the same permission — a collision that only surfaces once the roles themselves collapse. Both passes de-duplicate before repointing.

With teams enabled, roles are matched on `(team, name)` and pivot rows are de-duplicated per team.

### 3. guardian:policies

Generates Laravel policy classes for your Filament resources, wired to the permissions synced by `guardian:sync`. Run this during development when you add a new resource or need to regenerate existing policies.

```bash
# Interactive mode — prompts for panel and resources
php artisan guardian:policies

# Generate for all resources in a panel
php artisan guardian:policies --panel=admin --all-resources

# Generate for all panels at once
php artisan guardian:policies --all-panels

# Regenerate and overwrite existing policies
php artisan guardian:policies --panel=admin --all-resources --force
```

Generated policies check permissions using `$user->can()`, keyed to the format defined in your config:

```php
// app/Policies/UserPolicy.php
public function viewAny(User $user): bool
{
    return $user->can('ViewAny:User');
}

public function update(User $user, User $model): bool
{
    return $user->can('Update:User');
}
```

### 4. guardian:create-user

Creates a user account. Most useful on first deployment when your database is empty and you need an initial account to access the panel.

```bash
# Interactive mode — prompts for name, email, password
php artisan guardian:create-user

# Non-interactive, useful for CI/CD or scripts
php artisan guardian:create-user --name="Admin" --email="admin@example.com" --password="secret"
```

If your User model has additional required fields, register a creation callback in your `AppServiceProvider` to handle them:

```php
use Waguilar\FilamentGuardian\Facades\Guardian;
use Illuminate\Support\Facades\Hash;

public function boot(): void
{
    Guardian::createUserUsing(function (string $userModel, array $data) {
        return $userModel::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
            'is_admin' => true, // any additional fields your model requires
        ]);
    });
}
```

First deployment sequence:

```bash
php artisan migrate
php artisan guardian:sync
php artisan guardian:create-user --name="Admin" --email="admin@example.com" --password="changeme"
php artisan guardian:super-admin --panel=admin --email="admin@example.com"
```

### 5. guardian:super-admin

Creates the super-admin role for a non-tenant panel and optionally assigns it to a user. For tenant panels, this role is created automatically when a tenant is created — this command is only needed for panels without tenancy.

```bash
# Create the super-admin role for a panel
php artisan guardian:super-admin --panel=admin

# Create the role and assign it to an existing user
php artisan guardian:super-admin --panel=admin --email=admin@example.com
```

| Panel type | Role creation |
|------------|---------------|
| Non-tenant | Run this command manually |
| Tenant | Automatic when a tenant is created |

## Policy Configuration

Controls how `guardian:policies` generates Laravel policy classes. The defaults cover the full set of Filament resource actions — adjust them to match what your application actually uses.

```php
'policies' => [
    'path'   => app_path('Policies'),  // where policy files are written
    'merge'  => true,                  // merge resource-specific methods with defaults (false = replace)
    'methods' => [
        'viewAny', 'view', 'create', 'update', 'delete',
        'restore', 'forceDelete', 'deleteAny', 'restoreAny',
        'forceDeleteAny', 'replicate', 'reorder',
    ],
    'single_parameter_methods' => [    // methods that receive only $user, not $model
        'viewAny', 'create', 'deleteAny', 'restoreAny',
        'forceDeleteAny', 'reorder',
    ],
],
```

**`merge`** — when `true`, any methods added via `resources.manage` for a specific resource are combined with the global `methods` list. When `false`, they replace it entirely for that resource.

**`single_parameter_methods`** — collection-level actions (e.g., `viewAny`, `create`) only receive the authenticated user; they have no model instance to work with. Methods not in this list receive both `$user` and `$model`.

### Per-Resource Configuration

Override how permissions and policies are generated for specific resources, or skip them entirely:

```php
'resources' => [
    'subject' => 'model',  // 'model' uses the model name; 'class' uses the resource class name
    'manage'  => [
        App\Filament\Resources\Blog\CategoryResource::class => [
            'subject' => 'BlogCategory',           // override the permission key subject
        ],
        App\Filament\Resources\RoleResource::class => [
            'methods' => ['viewAny', 'view', 'create'], // limit generated policy methods
        ],
    ],
    'exclude' => [
        App\Filament\Resources\SettingsResource::class,  // skip entirely
    ],
],
```

### Pages & Widgets Configuration

Pages and widgets each get a single permission by default — typically a `view` prefix applied to the page or widget class name. Both sections follow the same structure:

```php
'pages' => [
    'subject' => 'class',  // derive subject from the class name
    'prefix'  => 'view',   // action prefix: View:Dashboard, View:Settings, etc.
    'exclude' => [
        Filament\Pages\Dashboard::class,  // excluded by default
    ],
],

'widgets' => [
    'subject' => 'class',
    'prefix'  => 'view',
    'exclude' => [
        Filament\Widgets\AccountWidget::class,      // excluded by default
        Filament\Widgets\FilamentInfoWidget::class, // excluded by default
    ],
],
```

Filament's built-in Dashboard, AccountWidget, and FilamentInfoWidget are excluded by default — they're framework-level components that most apps don't need to permission-gate.

## Resource-Based Policies

By default, every resource's authorization resolves through the model it's bound to — `UserResource` resolves to `UserPolicy`, `ProductResource` to `ProductPolicy`. This breaks when **two resources share the same model** but represent different workflows that need separate permissions.

A common example: a `PendingOrderResource` that lists `Order` records awaiting approval. The model is `Order`, but the operations belong to an approval workflow, not general order management. If an `OrderResource` already exists, both resources resolve to the same `OrderPolicy` and any permission you generate for `PendingOrderResource` is silently superseded by the policy bound to the model.

The Per-Resource Configuration above can give the two resources distinct **permission keys**, but it can't change which policy class Filament invokes. That's what this section solves.

### 1. Opt in with the trait

Add `HasResourcePolicy` to the resource. That's the entire setup — no extra properties, no config changes:

```php
use Filament\Resources\Resource;
use Waguilar\FilamentGuardian\Concerns\HasResourcePolicy;

class PendingOrderResource extends Resource
{
    use HasResourcePolicy;

    protected static ?string $model = Order::class;
}
```

Resources without the trait keep the default behaviour.

### 2. How names are derived

The `Resource` suffix is stripped from the resource class name, and the result drives both the permission keys and the generated policy class:

| Derived | Example |
|---------|---------|
| Permission keys | `PendingOrderResource` → `ViewAny:PendingOrder`, `Update:PendingOrder` |
| Policy class | `App\Policies\PendingOrderPolicy` (namespace from `policies.path`) |

Two resources opting into the trait get distinct policies and permission keys, even when they share a model.

### 3. Workflow

After adding the trait, generate the policy and sync permissions:

```bash
php artisan guardian:policies --panel=admin
php artisan guardian:sync
```

> **Important:** The plugin only registers the policy for resources whose policy class file already exists. If you add the trait but skip `guardian:policies`, the resource's authorization fails on the next request — by design, so missing policies surface as clear errors rather than falling back silently to the model's policy.

### 4. What you need to update in your application

Three places in Filament route around the resource's authorization helper. Each needs an adjustment when you opt a resource into a custom policy.

#### Relation managers

Declare `$relatedResource` on each relation manager owned by the resource:

```php
class OrderItemsRelationManager extends RelationManager
{
    protected static ?string $relatedResource = PendingOrderResource::class;
}
```

Without it, the relation manager authorizes against the related model directly and bypasses your custom policy.

#### Action authorization

Actions with `->authorize('update')` (the string form) call Laravel's gate directly against the model, bypassing the resource. Use the default form or a closure instead:

```php
EditAction::make();                                  // routes through the resource
EditAction::make()->authorize(fn ($record) =>
    PendingOrderResource::can('update', $record));   // routes through the resource
EditAction::make()->authorize('update');             // bypasses the resource
```

The same applies to `BulkAction`, `HeaderAction`, and table actions.

#### Hand-written `$user->can(...)` calls

Use the resource class — not the model — when checking against a custom-policy resource:

```php
// Collection actions (viewAny, create, deleteAny, ...)
$user->can('viewAny', PendingOrderResource::class);

// Record actions — pass the record as the second array element
$user->can('update', [PendingOrderResource::class, $record]);
```

Generated record-action methods like `update($user, $record)` require the record argument; passing only the class string throws a `TypeError`.

### 5. Out of scope

**Top-level Filament Pages** use a different authorization mechanism. The trait does not apply — override `canAccess()` directly to gate them with a custom policy.

**Clusters** are authorization-neutral. Placing a resource inside a cluster doesn't add or change any gate checks.

## Relation Manager Policies

Filament's RelationManagers are first-class authorization subjects: tab visibility goes through `RelationManager::canViewForRecord`, and per-action checks (create / update / delete / view) flow through `getAuthorizationResponse`. Both default to authorizing against the **related model's** policy.

That works the moment your application has a model-bound policy. Without one, projects historically chose between `$shouldSkipAuthorization = true` (no auth) or a hidden "shim" Resource just to make Guardian generate a policy. Neither is great.

Guardian discovers RelationManagers natively. If your relation manager has neither `$relatedResource` nor `$shouldSkipAuthorization`, and the related model isn't already owned by a registered Resource, Guardian treats it like a Resource for policy and permission generation.

### 1. Auto-discovery

When you run `php artisan guardian:policies`, the command walks each registered Resource's `getRelations()` **and** the overridden `getAllRelationManagers()` on every page using Filament's `HasRelationManagers` concern (`ViewRecord`, `EditRecord`, `ManageRelatedRecords`, and any custom page using the concern), then offers eligible relation managers alongside resources. Both registration sites are supported; results are deduplicated by relation manager class. `php artisan guardian:sync` syncs their permissions automatically, with no extra flag needed.

> **Page-level overrides:** when a page overrides `getAllRelationManagers()` to scope relation managers to that page only — a common Filament pattern (View-page-only, Edit-page-only, or any page-specific subset) — Guardian discovers them via reflection on a stub page instance. Pages that depend on runtime state (e.g. `$this->record`) during this method are silently skipped — they can't be reached without an owner record.

A relation manager is **skipped** when ANY of these is true:

- `$relatedResource = SomeResource::class` is set — Filament already routes auth through that resource.
- `$shouldSkipAuthorization = true` is set — explicit opt-out.
- The related model is already bound to a registered Resource — that Resource's policy wins by precedence.
- The class appears in `config('filament-guardian.relation_managers.exclude')`.

### 2. Subject derivation

By default, the permission subject is the **related model's class basename**. For an `AccountIdentificationsRelationManager` whose `accountIdentifications()` relation returns `AccountIdentification` records, the generated permissions are `ViewAny:AccountIdentification`, `Update:AccountIdentification`, etc., and the policy file is `App\Policies\AccountIdentificationPolicy`.

Switch to the relation manager's class basename (minus the `RelationManager` suffix) globally:

```php
// config/filament-guardian.php
'relation_managers' => [
    'subject' => 'class', // 'model' (default) or 'class'
    'manage' => [
        // \App\Filament\Resources\Accounts\RelationManagers\IdentificationsRelationManager::class => [
        //     'subject' => 'AccountIdentity',
        //     'methods' => ['viewAny', 'view', 'create', 'update', 'delete'],
        // ],
    ],
    'exclude' => [
        // \App\Filament\Resources\Accounts\RelationManagers\AuditLogRelationManager::class,
    ],
],
```

#### Section label

The card label shown in the role-management UI defaults to a humanized plural of the related model's class name (e.g. `AccountIdentification` → "Account Identifications"). Override it by either:

- Setting the relation manager's static `$pluralModelLabel` property (Filament's standard label property, still respected in v5):

  ```php
  class IdentificationsRelationManager extends RelationManager
  {
      protected static ?string $pluralModelLabel = 'Identificaciones';
  }
  ```

- Adding a `label` entry to the config override:

  ```php
  'manage' => [
      \App\Filament\RelationManagers\IdentificationsRelationManager::class => [
          'label' => 'Identificaciones',
      ],
  ],
  ```

The config entry takes priority. Both produce the same outcome — pick the one that fits where you'd rather declare it.

### 3. Opt-in: `HasRelationManagerPolicy`

Use this only when the related model **is** owned by a registered Resource and you want the relation manager to authorize against a separate policy. Without the trait, the precedence rule keeps the Resource's policy authoritative.

```php
use Filament\Resources\RelationManagers\RelationManager;
use Waguilar\FilamentGuardian\Concerns\HasRelationManagerPolicy;

class OrderItemsRelationManager extends RelationManager
{
    use HasRelationManagerPolicy;

    protected static string $relationship = 'items';
}
```

The trait does two things:

- Bypasses the precedence rule, so generation creates `OrderItemsPolicy` (suffix `RelationManager` stripped) keyed on the relation manager class instead of the model.
- Overrides `canViewForRecord` and `getAuthorizationResponse` to call Gate against `static::class` (the relation manager) rather than the related model — exactly as `HasResourcePolicy` does for resources.

Run `guardian:policies` then `guardian:sync` and the new policy and permissions appear, plus a card on the role form for the relation manager subject.

### 4. What you need to update in your application

Same three pitfalls as the Resource-Based Policies section, with the relation manager class in the role of the resource class.

#### `$relatedResource` on the relation manager

When `$relatedResource` is set, Filament routes auth through that Resource's policy — Guardian's relation manager pipeline doesn't fire, and the Resource section above is the relevant guidance. This section assumes `$relatedResource` is unset.

#### Action authorization

Actions with `->authorize('update')` (the string form) call Laravel's gate directly against the model. Use the default form or a closure:

```php
EditAction::make();                                         // routes through the relation manager
EditAction::make()->authorize(fn (Model $record) =>
    Gate::check('update', [OrderItemsRelationManager::class, $record]));
EditAction::make()->authorize('update');                    // bypasses the relation manager's policy
```

#### Hand-written `$user->can(...)` calls

Pass the relation manager class — not the model — when you've opted into `HasRelationManagerPolicy`:

```php
// Collection actions (viewAny, create, deleteAny, ...)
$user->can('viewAny', OrderItemsRelationManager::class);

// Record actions — pass the record as the second array element
$user->can('update', [OrderItemsRelationManager::class, $record]);
```

For relation managers without the trait, permissions resolve against the model's policy as Laravel normally does — `$user->can('update', $orderItem)` works.

### 5. Out of scope

**Nested resources** (Filament's `parentResource` / `getParentResourceRegistration` mechanism) are real Resources. They're already discovered through `$panel->getResources()` and follow the Resource-Based Policies path.

**Closure-resolved relation managers inside `RelationGroup`** can't be statically discovered without an owner record. Register them as plain class-string entries or as `RelationManagerConfiguration` instances if you want Guardian to see them.

## Publishing

### 1. Config

Publish the config file to customize global defaults, permission key format, custom permissions, and policy generation settings:

```bash
php artisan vendor:publish --tag="filament-guardian-config"
```

### 2. Translations

Publish the translation files to customize any label the package outputs:

```bash
php artisan vendor:publish --tag="filament-guardian-translations"
```

### 3. Multitenancy migration

Only needed when your application has both tenant and non-tenant panels. Publishes the migration that makes the Spatie pivot tables compatible with mixed-panel setups — see the [Multi-Tenancy](#multi-tenancy) section for full context.

```bash
php artisan vendor:publish --tag="filament-guardian-multitenancy"
php artisan migrate
```

## Configuration Priority Order

All configurable values resolve top-down — the first source that has a value wins.

1. **Local override** — If you've published the RoleResource and declared a static property on your subclass (e.g. `protected static ?string $navigationIcon = 'heroicon-o-lock-closed'`), that value takes priority over everything else. This uses PHP late static binding, so the subclass declaration wins at the class level without any runtime checks.

2. **Fluent API** — Values set via `FilamentGuardianPlugin::make()->navigationIcon(...)` in your panel provider. These are per-panel, so different panels can have different values independently.

3. **Config file** — Global defaults from `config/filament-guardian.php`. These apply to all panels unless overridden by the fluent API or a local static property.

4. **Translation file** — Applies to labels only (navigation label, model label, etc.). If no value is set above, the package looks for a translation key before falling through to the hardcoded default.

5. **Hardcoded default** — The value the package ships with. You'll only reach this if nothing above provides a value.

## Role Resource UI

Configure how the RoleResource appears in your panel — navigation placement, labels, URLs, form sections, tabs, and permission checkboxes — all through the plugin fluent API without publishing the resource.

### 1. Navigation

Configure the RoleResource's position and appearance in the sidebar:

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

### 2. Cluster

Place the RoleResource inside a Filament cluster so it appears under that cluster's sub-navigation. Pass the cluster class directly:

```php
FilamentGuardianPlugin::make()
    ->cluster(\App\Filament\Clusters\Settings::class)
```

Closures are supported for conditional assignment:

```php
FilamentGuardianPlugin::make()
    ->cluster(fn () => auth()->user()->isAdmin()
        ? \App\Filament\Clusters\Settings::class
        : null
    )
```

Or set it globally in the config file:

```php
// config/filament-guardian.php
'role_resource' => [
    'navigation' => [
        'cluster' => \App\Filament\Clusters\Settings::class,
    ],
],
```

Defaults to `null` — no cluster.

### 3. Resource Labels & Slug

Customize how the resource is named in the UI and what URL it uses:

```php
FilamentGuardianPlugin::make()
    ->modelLabel('Role')
    ->pluralModelLabel('Roles')
    ->slug('access-roles')  // URL: /admin/access-roles
```

### 4. Section Configuration

The role form is divided into two sections — a role details section at the top containing the name field, and a permissions section below containing the tabs and the select-all toggle. Both can be configured independently.

**Role section** — label, description, icon, and layout:

```php
FilamentGuardianPlugin::make()
    ->roleSectionLabel('Role Details')
    ->roleSectionDescription('Configure basic role settings')
    ->roleSectionIcon(Heroicon::OutlinedIdentification)
    ->roleSectionAside() // renders the section in an aside layout
```

**Permissions section** — label, description, and icon:

```php
FilamentGuardianPlugin::make()
    ->permissionsSectionLabel('Permissions')
    ->permissionsSectionDescription('Select which actions this role can perform')
    ->permissionsSectionIcon(Heroicon::OutlinedLockClosed)
```

Pass `false` to any icon method to remove it entirely:

```php
FilamentGuardianPlugin::make()
    ->roleSectionIcon(false)
    ->permissionsSectionIcon(false)
```

All methods accept closures for dynamic values.

### 5. Tab Configuration

The permissions section renders up to four tabs — Resources, Pages, Widgets, and Custom. The Resources tab is always shown. The other three only appear when there is something to display: Pages and Widgets tabs require those permission types to exist in the database (synced via `guardian:sync`), and the Custom tab only appears when custom permissions are defined in your config.

You can force-hide any tab regardless of whether it has content:

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

### 6. Tab Icons

Customize the icon shown on each tab:

```php
use Filament\Support\Icons\Heroicon;

FilamentGuardianPlugin::make()
    ->resourcesTabIcon(Heroicon::OutlinedRectangleStack)
    ->pagesTabIcon(Heroicon::OutlinedDocument)
    ->widgetsTabIcon(Heroicon::OutlinedPresentationChartBar)
    ->customTabIcon(Heroicon::OutlinedWrench)
```

| Tab | Default icon |
|-----|-------------|
| Resources | `Heroicon::OutlinedSquare3Stack3d` |
| Pages | `Heroicon::OutlinedDocumentText` |
| Widgets | `Heroicon::OutlinedChartBar` |
| Custom | `Heroicon::OutlinedCog6Tooth` |

### 7. Checkbox Layout

Controls how permission checkboxes are arranged within each tab — how many columns they use and which direction they flow. Global settings apply to all tabs; per-tab values take priority over the global ones.

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

Responsive arrays are supported for all column methods:

```php
FilamentGuardianPlugin::make()
    ->permissionCheckboxColumns([
        'sm' => 2,
        'md' => 3,
        'lg' => 4,
    ])
```

### 8. Resource Sections

Within the Resources tab, permissions are grouped by resource — each resource gets its own collapsible section. You can control whether sections start collapsed, how many columns their permission checkboxes span, and whether the resource's navigation icon is shown in the section header.

```php
FilamentGuardianPlugin::make()
    ->collapseResourceSections()    // start all resource sections collapsed
    ->resourceSectionColumns(2)     // permission checkboxes span 2 columns per resource
    ->showResourceSectionIcon()     // show the resource's navigation icon in the header
```

### 9. Search & Permission Icons

Each tab includes a search input to filter permissions by name. The `permissionAssignedIcon` appears next to each assigned permission in the view infolist. Pass `false` to either to hide it.

```php
FilamentGuardianPlugin::make()
    ->searchIcon(Heroicon::OutlinedMagnifyingGlass)
    ->permissionAssignedIcon(Heroicon::OutlinedCheckCircle)
```

### 10. Select All Toggle

The Select All toggle in the permissions section header selects or deselects all permissions at once. You can customize the icon for each state or hide them by passing `false`.

```php
FilamentGuardianPlugin::make()
    ->selectAllOnIcon(Heroicon::OutlinedCheckCircle)
    ->selectAllOffIcon(Heroicon::OutlinedXCircle)
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

### 11. Permission Labels

The label shown on each permission checkbox is derived automatically based on type:

| Type | Label source |
|------|-------------|
| Resources | `Resource::getPluralModelLabel()` |
| Pages | `Page::getNavigationLabel()` |
| Widgets | `Widget::getHeading()` or humanized class name |
| Custom | Translation file or permission key |

### 12. Content Tabs (Edit / View pages)

By default the View page renders the role infolist on top, and the Users relation manager underneath. Combine them into a tabbed layout where the page content sits beside the relation manager tabs:

```php
FilamentGuardianPlugin::make()
    ->combineRelationManagerTabsWithContent()        // shortcut: enables on BOTH pages
    ->contentTabLabel('Settings')                    // override the content tab label
    ->contentTabIcon(Heroicon::OutlinedCog6Tooth)    // icon for the content tab
    ->contentTabPosition(ContentTabPosition::After)  // place after the relation tabs
```

Need different behavior on each page? Use the per-page setters — they override the shortcut:

```php
FilamentGuardianPlugin::make()
    ->combineRelationManagerTabsWithContentOnView()        // tabbed layout on View only
    ->combineRelationManagerTabsWithContentOnEdit(false)   // keep Edit form-only
```

`contentTabPosition()` accepts the `ContentTabPosition` enum or the strings `'before'` / `'after'`. Filament's default places the content tab before the relation tabs.

Or via config:

```php
// config/filament-guardian.php
'role_resource' => [
    'content_tabs' => [
        'combine_relation_manager_tabs' => false,         // default for both pages
        'combine_relation_manager_tabs_on_edit' => null,  // null = inherit default
        'combine_relation_manager_tabs_on_view' => true,  // override per page
        'label' => 'Settings',
        'icon' => 'heroicon-o-cog-6-tooth',
        'position' => 'after',
    ],
],
```

Resolution order for the combine flag (most specific wins, fluent over config within each tier):

1. Per-page fluent setter — `combineRelationManagerTabsWithContentOnEdit/OnView`
2. Global fluent setter — `combineRelationManagerTabsWithContent`
3. Per-page config — `combine_relation_manager_tabs_on_edit/_on_view`
4. Global config — `combine_relation_manager_tabs` (default `false`)

> **Note:** the package hides relation managers on the Edit page by default. Enabling combine on Edit (via the shortcut or the on-edit setter) automatically opts in the resource's relation managers there too, so the form sits beside the relation tabs. Without it, Edit stays form-only.

---

All fluent API methods throughout this section accept closures for dynamic values.

## Extending the Role Resource

The fluent API above lets you configure the role resource without touching PHP files. When you need deeper customization — overriding form schemas, page layouts, table actions, or anything else the plugin API doesn't expose — publish the resource files into your application:

```bash
php artisan filament-guardian:publish-role-resource {panel?}
```

Published classes extend base classes from the package. You only override what you actually need — the base classes handle all the standard logic.

### 1. Available Base Classes

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

### 2. Example: Custom Table Actions

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

### 3. Example: Custom View Page

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

### 4. Using the Facade

When building a custom role form, use `Guardian::uniqueRoleValidation()` to enforce unique role names while correctly ignoring the current record on edit:

```php
use Waguilar\FilamentGuardian\Facades\Guardian;

TextInput::make('name')
    ->required()
    ->unique(
        ignoreRecord: true,
        modifyRuleUsing: Guardian::uniqueRoleValidation(),
    )
```

## Users Relation Manager

The Role resource includes a Users tab on the view page, backed by a relation manager that lets you attach and detach users directly from a role — no need to navigate to each user individually.

The tab shows users assigned to the role with Name and Email columns, supports search and bulk operations, and automatically excludes users who already hold the super-admin role from the attach dropdown.

### 1. Customization

When you publish the Role resource, a `UsersRelationManager` stub is included. You can extend it to customize the table or override any part of the relation manager:

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

`ManageUserPermissionsAction` is a table action you add to your UserResource to open a slide-over for managing a user's direct permissions — permissions assigned specifically to that user, on top of what they already inherit through roles.

### 1. Adding the Action

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

> **Eager-load the roles relationship.** The action hides itself for super-admins, which means it checks the user's roles once per row — a query per row unless the table loads them up front:
>
> ```php
> return $table
>     ->modifyQueryUsing(fn (Builder $query) => $query->with('roles'))
>     ->columns([...])
> ```
>
> On a 50-row page that's the difference between 51 queries and 2.

### 2. Behavior

The slide-over displays the user's name and email at the top so it's always clear whose permissions you're editing. The permission UI follows the same tab format as the role resource — Resources, Pages, Widgets, and Custom — with the same search and select-all toggle.

A few things happen automatically:

- **Scoped to the panel's guard** — only permissions belonging to the current panel's auth guard are listed, ticked, or saved. Direct permissions the user holds under another panel's guard are left untouched when you save
- **Role permissions excluded** — permissions already granted through roles are not shown; they're managed at the role level. Roles belonging to a different guard don't count, since this panel never checks them
- **Role permissions notice** — a warning shows how many permissions the user already has from their roles
- **Hidden for super-admins** — the action doesn't appear for super-admin users since they bypass all permission checks
- **Automatic cleanup** — when saved, any direct permissions that are now also covered by a role are removed to avoid redundancy

### 3. Customization

The action extends Filament's standard `Action`, so all fluent methods are available:

```php
ManageUserPermissionsAction::make()
    ->label('Custom Label')
    ->icon('heroicon-o-key')
    ->color('primary')
```

## Translations

English and Spanish translations ship by default. Publish the translation files to override any string the package outputs — including permission action labels, custom permission display names, and all role resource UI text:

```bash
php artisan vendor:publish --tag=filament-guardian-translations
```

This publishes to `lang/vendor/filament-guardian/{locale}/filament-guardian.php`.

| Key | What it controls |
|-----|-----------------|
| `roles.*` | Role resource labels, section titles, tab names, messages |
| `users.permissions.*` | User direct permissions modal labels |
| `actions.*` | Permission action labels (viewAny, create, update, etc.) |
| `custom.*` | Custom permission display names (overrides config labels) |
| `super_admin.*` | Super admin role messages and error strings |

## Testing

```bash
composer test     # run the test suite
composer analyse  # run PHPStan static analysis
composer lint     # run code style checks
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
