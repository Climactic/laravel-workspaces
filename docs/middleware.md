# Middleware

## Available Middleware

| Alias | Class | Purpose |
|-------|-------|---------|
| `workspace` | `SetWorkspaceContext` | Sets current workspace from request |
| `workspace.access` | `EnsureWorkspaceAccess` | Ensures user is workspace member |
| `workspace.role` | `EnsureWorkspaceRole` | Ensures user has required role |

## Basic Usage

```php
// routes/web.php
Route::middleware(['auth', 'workspace'])->group(function () {
    // Current workspace is set from user's current workspace
});

Route::middleware(['auth', 'workspace', 'workspace.access'])->group(function () {
    // Only workspace members can access
});

Route::middleware(['auth', 'workspace', 'workspace.role:admin,owner'])->group(function () {
    // Only admins and owners can access
});
```

## SetWorkspaceContext

Resolves and sets the current workspace using configured resolvers:

```php
// config/workspaces.php
'context' => [
    'resolvers' => [
        AuthUserResolver::class,      // From user's current_workspace
        RouteParameterResolver::class, // From route {workspace}
        HeaderResolver::class,         // From X-Workspace-Id header
        SubdomainResolver::class,      // From subdomain
        SessionResolver::class,        // From session
    ],
],
```

## EnsureWorkspaceAccess

Throws `WorkspaceAccessDeniedException` if:
- No current workspace is set
- User is not a member of the workspace

## EnsureWorkspaceRole

```php
// Single role
Route::middleware('workspace.role:admin');

// Multiple roles (OR)
Route::middleware('workspace.role:admin,owner');
```

Throws `WorkspaceAccessDeniedException` if user doesn't have any of the specified roles.

## Exceptions

```php
use Climactic\Workspaces\Exceptions\NoCurrentWorkspaceException;
use Climactic\Workspaces\Exceptions\WorkspaceAccessDeniedException;

// In exception handler
public function render($request, Throwable $e)
{
    if ($e instanceof NoCurrentWorkspaceException) {
        return redirect()->route('workspaces.select');
    }

    if ($e instanceof WorkspaceAccessDeniedException) {
        abort(403, 'You do not have access to this workspace.');
    }
}
```

## Custom Middleware

Override in config:

```php
'middleware' => [
    'set_context' => App\Http\Middleware\CustomSetWorkspaceContext::class,
    'ensure_access' => App\Http\Middleware\CustomEnsureWorkspaceAccess::class,
    'ensure_role' => App\Http\Middleware\CustomEnsureWorkspaceRole::class,
],
```
