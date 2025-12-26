# Events

## Available Events

### MemberAdded
Fired when a member is added to a workspace.

```php
use Climactic\Workspaces\Events\MemberAdded;

Event::listen(MemberAdded::class, function ($event) {
    $event->workspace; // The workspace
    $event->user;      // The added user
    $event->role;      // The assigned role
});
```

### MemberRemoved
Fired when a member is removed from a workspace.

```php
use Climactic\Workspaces\Events\MemberRemoved;

Event::listen(MemberRemoved::class, function ($event) {
    $event->workspace;
    $event->user;
});
```

### MemberRoleUpdated
Fired when a member's role is changed.

```php
use Climactic\Workspaces\Events\MemberRoleUpdated;

Event::listen(MemberRoleUpdated::class, function ($event) {
    $event->workspace;
    $event->user;
    $event->oldRole;
    $event->newRole;
});
```

## Registering Listeners

```php
// EventServiceProvider.php
protected $listen = [
    \Climactic\Workspaces\Events\MemberAdded::class => [
        \App\Listeners\SendWelcomeNotification::class,
    ],
    \Climactic\Workspaces\Events\MemberRemoved::class => [
        \App\Listeners\RevokeAccess::class,
    ],
];
```

## Auto-Create Workspace on Registration

The package can automatically create a workspace when a user registers:

```php
// config/workspaces.php
'auto_create_on_registration' => [
    'enabled' => true,
    'name_from' => 'name',           // User attribute for workspace name
    'name_suffix' => "'s Workspace", // e.g., "John's Workspace"
    'listen_to' => Registered::class,
],
```

To disable:
```php
'auto_create_on_registration' => [
    'enabled' => false,
],
```
