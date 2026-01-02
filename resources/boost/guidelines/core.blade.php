## Laravel Workspaces

This package provides multi-tenant workspace/team functionality for Laravel applications, including member management, role-based permissions, invitations, and automatic data scoping.

### Setup Conventions

- **User Model**: Add the `HasWorkspaces` trait to your User model
- **Workspace-Scoped Models**: Add the `BelongsToWorkspace` trait to models that should be isolated per workspace
- **Custom Workspace Model**: Implement `WorkspaceContract` and use `ImplementsWorkspace` trait

### Features

- **Creating Workspaces**: Use the `Workspaces` facade or action classes.

@verbatim
<code-snippet name="Create a workspace" lang="php">
use Climactic\Workspaces\Facades\Workspaces;

// Via Facade
$workspace = Workspaces::createWorkspace(['name' => 'Team Name'], $owner);

// Via Action class (more control)
use Climactic\Workspaces\Actions\CreateWorkspace;
$workspace = app(CreateWorkspace::class)->execute(
    ['name' => 'Team Name'],
    $user,
    setAsCurrent: true
);
</code-snippet>
@endverbatim

- **Managing Members**: Add, update roles, or remove members from workspaces.

@verbatim
<code-snippet name="Manage workspace members" lang="php">
// Add member with role
$workspace->addMember($user, 'admin');

// Update member role
Workspaces::updateMemberRole($workspace, $user, 'member');

// Remove member
$workspace->removeMember($user);
</code-snippet>
@endverbatim

- **Invitations**: Invite users via email with configurable roles.

@verbatim
<code-snippet name="Send and accept invitations" lang="php">
use Climactic\Workspaces\Facades\Workspaces;

// Send invitation
$invitation = Workspaces::invite($workspace, 'email@example.com', 'member', $invitedBy);

// Accept invitation (by the invited user)
$workspace = Workspaces::acceptInvitation($token, $user);

// Cancel or decline
Workspaces::cancelInvitation($invitation);
Workspaces::declineInvitation($invitation);
</code-snippet>
@endverbatim

- **Switching Workspaces**: Users can switch between their workspaces.

@verbatim
<code-snippet name="Switch workspace context" lang="php">
$user->switchWorkspace($workspace);
$current = $user->currentWorkspace;
$currentId = $user->current_workspace_id;
</code-snippet>
@endverbatim

- **Permission & Role Checking**: Check user permissions and roles within a workspace.

@verbatim
<code-snippet name="Check permissions and roles" lang="php">
// Check specific permission
$user->hasWorkspacePermission($workspace, 'members.invite');

// Check role(s)
$user->hasWorkspaceRole($workspace, ['admin', 'owner']);

// Convenience methods
$user->isWorkspaceOwner($workspace);
$user->isWorkspaceAdmin($workspace);
$user->belongsToWorkspace($workspace);
</code-snippet>
@endverbatim

- **Data Scoping**: Models with `BelongsToWorkspace` trait are automatically filtered to current workspace.

@verbatim
<code-snippet name="Automatic data scoping" lang="php">
use Climactic\Workspaces\Concerns\BelongsToWorkspace;

class Project extends Model
{
    use BelongsToWorkspace;
}

// Queries automatically scoped to current workspace
Project::all(); // Only current workspace projects

// Query across all workspaces
Project::allWorkspaces()->get();

// Query specific workspace
Project::forWorkspace($workspace)->get();
</code-snippet>
@endverbatim

### Middleware

- `workspace` - Sets the current workspace context from configured resolvers
- `workspace.access` - Requires authenticated user to be a member of current workspace
- `workspace.role:admin,owner` - Requires user to have one of the specified roles

@verbatim
<code-snippet name="Route middleware usage" lang="php">
Route::middleware(['auth', 'workspace', 'workspace.access'])->group(function () {
    Route::get('/dashboard', DashboardController::class);
});

Route::middleware(['auth', 'workspace', 'workspace.role:admin,owner'])->group(function () {
    Route::post('/members', AddMemberController::class);
});
</code-snippet>
@endverbatim

### Events

The package dispatches these events for custom listeners:

- `WorkspaceCreated`, `WorkspaceDeleted`, `WorkspaceSwitched`
- `MemberAdded`, `MemberRemoved`, `MemberRoleUpdated`, `OwnershipTransferred`
- `InvitationCreated`, `InvitationAccepted`, `InvitationDeclined`, `InvitationCancelled`

### Context Resolvers

Configure how the current workspace is determined in `config/workspaces.php`:

- `AuthUserResolver` (default) - From user's `is_current` membership flag
- `SubdomainResolver` - From request subdomain (e.g., `acme.yourapp.com`)
- `RouteParameterResolver` - From route parameter (e.g., `/workspace/{workspace}`)
- `HeaderResolver` - From HTTP header (default: `X-Workspace-Id`)
- `SessionResolver` - From session storage

### Default Roles

The package includes three default roles (customizable in config):

- `owner` - Full access (`['*']` permissions)
- `admin` - Can manage members and settings
- `member` - Standard read access
