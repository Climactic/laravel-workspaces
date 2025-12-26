# Actions

Actions encapsulate business logic. All actions are customizable via config.

## Available Actions

| Action | Purpose |
|--------|---------|
| `CreateWorkspace` | Create a new workspace |
| `DeleteWorkspace` | Delete a workspace |
| `AddWorkspaceMember` | Add a member |
| `RemoveWorkspaceMember` | Remove a member |
| `UpdateMemberRole` | Change member's role |
| `CreateInvitation` | Create an invitation |
| `AcceptInvitation` | Accept an invitation |
| `DeclineInvitation` | Decline an invitation |
| `CancelInvitation` | Cancel an invitation |

## Using Actions

```php
use Climactic\Workspaces\Actions\CreateWorkspace;

$action = app(CreateWorkspace::class);
$workspace = $action->execute(
    name: 'My Team',
    owner: $user,
);
```

Or via invokable:

```php
$workspace = app(CreateWorkspace::class)(
    name: 'My Team',
    owner: $user,
);
```

## Action Examples

### CreateWorkspace
```php
$action->execute(
    name: 'My Team',
    owner: $user,
    description: 'Team description',
    setAsCurrent: true,
    personal: false,
);
```

### DeleteWorkspace
```php
$action->execute($workspace, force: true); // Force delete
$action->execute($workspace, force: false); // Soft delete
```

### AddWorkspaceMember
```php
$action->execute(
    workspace: $workspace,
    user: $user,
    role: 'admin',
    setAsCurrent: true,
);
```

### UpdateMemberRole
```php
$action->execute($workspace, $user, 'admin');
```

### CreateInvitation
```php
$action->execute(
    workspace: $workspace,
    email: 'user@example.com',
    inviter: $currentUser,
    role: 'member',
);
```

### AcceptInvitation
```php
$action->execute($invitation, $user);
// or by token
$action->execute($token, $user);
```

## Custom Actions

Override in config:

```php
// config/workspaces.php
'actions' => [
    'create_workspace' => App\Actions\CustomCreateWorkspace::class,
],
```

Your action should match the signature of the original:

```php
class CustomCreateWorkspace
{
    public function execute(
        string $name,
        ?Model $owner = null,
        ?string $description = null,
        bool $setAsCurrent = false,
        bool $personal = false,
    ): WorkspaceContract {
        // Custom logic
    }

    public function __invoke(...$args)
    {
        return $this->execute(...$args);
    }
}
```
