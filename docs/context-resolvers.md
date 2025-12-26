# Context Resolvers

Context resolvers determine the current workspace from the request.

## Configuration

```php
// config/workspaces.php
'context' => [
    'resolvers' => [
        \Climactic\Workspaces\ContextResolvers\AuthUserResolver::class,
    ],
],
```

## Available Resolvers

### AuthUserResolver
Gets workspace from authenticated user's `current_workspace_id`:
```php
$user->currentWorkspace;
```

### RouteParameterResolver
Gets workspace from route parameter:
```php
Route::get('/workspaces/{workspace}/settings', ...);
```

Config:
```php
'route_parameter' => [
    'name' => 'workspace',
    'field' => 'slug', // or 'id', 'uuid'
],
```

### HeaderResolver
Gets workspace from HTTP header:
```php
// Request header: X-Workspace-Id: 123
```

Config:
```php
'header' => [
    'name' => 'X-Workspace-Id',
],
```

### SubdomainResolver
Gets workspace from subdomain:
```php
// acme.myapp.com -> finds workspace with slug 'acme'
```

Config:
```php
'subdomain' => [
    'domain' => env('WORKSPACES_DOMAIN', 'myapp.com'),
],
```

### SessionResolver
Gets workspace from session:
```php
session(['current_workspace_id' => $workspace->id]);
```

Config:
```php
'session' => [
    'key' => 'current_workspace_id',
],
```

## Resolver Chain

Resolvers are tried in order. First one that returns a workspace wins:

```php
'resolvers' => [
    RouteParameterResolver::class, // Try route first
    HeaderResolver::class,         // Then header
    AuthUserResolver::class,       // Fall back to user's current
],
```

## Custom Resolver

```php
use Climactic\Workspaces\Contracts\ContextResolverContract;

class CustomResolver implements ContextResolverContract
{
    public function resolve(Request $request): ?WorkspaceContract
    {
        // Your logic here
        return Workspace::find($someId);
    }
}
```

Register in config:
```php
'resolvers' => [
    App\Resolvers\CustomResolver::class,
],
```
