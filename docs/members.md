# Members

## Adding Members

```php
// Add with default role
$workspace->addMember($user);

// Add with specific role
$workspace->addMember($user, 'admin');

// Add and set as user's current workspace
$workspace->addMember($user, 'member', setAsCurrent: true);
```

## Removing Members

```php
$workspace->removeMember($user);
```

## Checking Membership

```php
// Check if user is a member
$workspace->hasUser($user);

// Check from user side
$user->belongsToWorkspace($workspace);
$user->ownsWorkspace($workspace);
```

## Roles

```php
// Get user's role in workspace
$role = $workspace->getMemberRole($user);
// or
$role = $user->workspaceRole($workspace);

// Check if user has specific role
$workspace->hasUserWithRole($user, 'admin');
// or
$user->hasWorkspaceRole($workspace, 'admin');
$user->hasWorkspaceRole($workspace, ['admin', 'owner']); // any of

// Update role
$workspace->updateMemberRole($user, 'admin');
```

## Querying Members

```php
// Get all members
$workspace->members;

// Get all memberships (with pivot data)
$workspace->memberships;

// Get user's workspaces
$user->workspaces;
$user->ownedWorkspaces;
```

## Current Workspace

```php
// Switch user's current workspace
$user->switchWorkspace($workspace);

// Get user's current workspace
$user->currentWorkspace;

// Check if workspace is user's current
$user->isCurrentWorkspace($workspace);
```

## Personal Workspace

```php
// Get user's personal workspace
$user->personalWorkspace();

// Check if workspace is personal
$workspace->isPersonal();
```
