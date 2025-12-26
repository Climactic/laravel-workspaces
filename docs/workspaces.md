# Workspaces

## Creating a Workspace

```php
use Climactic\Workspaces\Models\Workspace;

// Basic creation
$workspace = Workspace::create([
    'name' => 'My Team',
    'owner_id' => $user->id,
]);

// Using the action
$action = app(\Climactic\Workspaces\Actions\CreateWorkspace::class);
$workspace = $action->execute(
    name: 'My Team',
    owner: $user,
    setAsCurrent: true,
    personal: false
);
```

## Current Workspace

```php
// Set current workspace
$workspace->makeCurrent();

// Get current workspace
$current = Workspace::current();

// Check if there's a current workspace
if (Workspace::checkCurrent()) {
    // ...
}

// Forget current workspace
$workspace->forgetCurrent();
```

## Execute in Workspace Context

```php
$workspace->execute(function ($workspace) {
    // Code runs with $workspace as current
    // Automatically restores previous context after
});
```

## Soft Deletes

Enabled by default in config:
```php
'soft_deletes' => true,
```

```php
$workspace->delete();           // Soft delete
$workspace->forceDelete();      // Permanent delete
Workspace::withTrashed()->get(); // Include deleted
```

## Custom Workspace Model

```php
// config/workspaces.php
'models' => [
    'workspace' => App\Models\Workspace::class,
],
```

Your model must implement `WorkspaceContract` and use `ImplementsWorkspace` trait:

```php
use Climactic\Workspaces\Contracts\WorkspaceContract;
use Climactic\Workspaces\Concerns\ImplementsWorkspace;

class Workspace extends Model implements WorkspaceContract
{
    use ImplementsWorkspace;
}
```
