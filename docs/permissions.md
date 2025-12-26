# Permissions

The package uses a config-based permission system where roles and permissions are defined in `config/workspaces.php`.

## Config-Based Permissions

Roles and permissions are defined in `config/workspaces.php`:

```php
'roles' => [
    'owner' => [
        'name' => 'Owner',
        'permissions' => ['*'], // All permissions
    ],
    'admin' => [
        'name' => 'Administrator',
        'permissions' => [
            'workspace.view',
            'workspace.update',
            'members.*', // Wildcard
        ],
    ],
    'member' => [
        'name' => 'Member',
        'permissions' => [
            'workspace.view',
            'members.view',
        ],
    ],
],

'permissions' => [
    'available' => [
        'workspace.view',
        'workspace.update',
        'workspace.delete',
        'members.view',
        'members.invite',
        // ...
    ],
],
```

## Checking Permissions

```php
// On user
$user->hasWorkspacePermission($workspace, 'members.invite');

// Get all permissions
$user->workspacePermissions($workspace);

// Check role
$user->hasWorkspaceRole($workspace, 'admin');
$user->hasWorkspaceRole($workspace, ['admin', 'owner']); // Any of these
```

---

## Permission Manager

The `PermissionManager` provides a unified API for permission checking:

```php
use Climactic\Workspaces\Permissions\PermissionManager;

$manager = app(PermissionManager::class);

// Get the active provider
$manager->getProvider();

// Permission checks
$manager->hasPermission($user, $workspace, 'members.view');
$manager->hasRole($user, $workspace, 'admin');
$manager->getRole($user, $workspace);         // 'admin'
$manager->getPermissions($user, $workspace);  // ['workspace.view', ...]

// Role management
$manager->assignRole($user, $workspace, 'admin');
$manager->removeRole($user, $workspace);

// Get available options
$manager->getAvailableRoles();       // ['owner', 'admin', 'member']
$manager->getAvailablePermissions(); // ['workspace.view', ...]
```

## Custom Permission Provider

Create your own provider by implementing `PermissionProviderContract`:

```php
// config/workspaces.php
'permissions' => [
    'provider' => App\Permissions\CustomProvider::class,
],
```

```php
use Climactic\Workspaces\Contracts\PermissionProviderContract;
use Illuminate\Database\Eloquent\Model;

class CustomProvider implements PermissionProviderContract
{
    public function hasPermission(Model $user, Model $workspace, string $permission): bool
    {
        // Your logic
    }

    public function hasRole(Model $user, Model $workspace, string|array $roles): bool
    {
        // Your logic
    }

    public function getPermissions(Model $user, Model $workspace): array
    {
        // Your logic
    }

    public function getRole(Model $user, Model $workspace): ?string
    {
        // Your logic
    }

    public function assignRole(Model $user, Model $workspace, string $role): void
    {
        // Your logic
    }

    public function removeRole(Model $user, Model $workspace): void
    {
        // Your logic
    }

    public function setWorkspaceContext(Model $workspace): void
    {
        // Your logic
    }

    public function getAvailableRoles(): array
    {
        // Your logic
    }

    public function getAvailablePermissions(): array
    {
        // Your logic
    }
}
```

## Middleware Authorization

Use middleware to protect routes by permission or role:

```php
// Require permission
Route::get('/settings', SettingsController::class)
    ->middleware('workspace.access:workspace.update');

// Require role
Route::delete('/workspace', DeleteController::class)
    ->middleware('workspace.role:owner');

// Require any of multiple roles
Route::post('/invite', InviteController::class)
    ->middleware('workspace.role:owner,admin');
```
