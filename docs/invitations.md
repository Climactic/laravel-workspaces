# Invitations

## Creating Invitations

```php
use Climactic\Workspaces\Actions\CreateInvitation;

$action = app(CreateInvitation::class);
$invitation = $action->execute(
    workspace: $workspace,
    email: 'user@example.com',
    inviter: $currentUser,
    role: 'member' // optional, defaults to config value
);
```

## Accepting Invitations

```php
use Climactic\Workspaces\Actions\AcceptInvitation;

$action = app(AcceptInvitation::class);

// Accept by invitation model
$action->execute($invitation, $user);

// Accept by token
$action->execute($token, $user);
```

## Declining Invitations

```php
use Climactic\Workspaces\Actions\DeclineInvitation;

$action = app(DeclineInvitation::class);
$action->execute($invitation);
```

## Canceling Invitations

```php
use Climactic\Workspaces\Actions\CancelInvitation;

$action = app(CancelInvitation::class);
$action->execute($invitation);
```

## Querying Invitations

```php
// Workspace invitations
$workspace->invitations;
$workspace->pendingInvitations;

// Query scopes
WorkspaceInvitation::pending()->get();
WorkspaceInvitation::expired()->get();
```

## Invitation Status

```php
$invitation->isValid();     // Not expired, accepted, or declined
$invitation->isPending();   // Same as isValid
$invitation->isExpired();
$invitation->isAccepted();
$invitation->isDeclined();
```

## Configuration

```php
// config/workspaces.php
'invitations' => [
    'enabled' => true,
    'expires_after_days' => 7,
    'notification' => WorkspaceInvitationNotification::class,
    'acceptance_url' => '/workspace-invitations/{token}/accept',
],
```

## Pruning Old Invitations

```bash
# Delete expired invitations
php artisan workspaces:prune-invitations --expired-only

# Delete invitations older than 30 days
php artisan workspaces:prune-invitations --days=30
```
