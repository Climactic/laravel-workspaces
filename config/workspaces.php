<?php

declare(strict_types=1);

// config for Climactic/Workspaces

return [
    /*
    |--------------------------------------------------------------------------
    | Workspace Models
    |--------------------------------------------------------------------------
    |
    | Customize which models to use. Use your own models that implement
    | the corresponding contracts, or use the package defaults.
    |
    */
    'models' => [
        'workspace' => \Climactic\Workspaces\Models\Workspace::class,
        'membership' => \Climactic\Workspaces\Models\WorkspaceMembership::class,
        'invitation' => \Climactic\Workspaces\Models\WorkspaceInvitation::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    |
    | The user model class used by your application.
    |
    */
    'user_model' => \App\Models\User::class,

    /*
    |--------------------------------------------------------------------------
    | Primary Key Type
    |--------------------------------------------------------------------------
    |
    | The type of primary key to use for workspace-related tables.
    | Supported: "id" (auto-increment), "uuid", "ulid"
    |
    */
    'primary_key_type' => 'id',

    /*
    |--------------------------------------------------------------------------
    | Table Names
    |--------------------------------------------------------------------------
    |
    | Customize table names if needed. The 'users' table is used for foreign
    | key references to your application's users table.
    |
    */
    'tables' => [
        'users' => 'users',
        'workspaces' => 'workspaces',
        'memberships' => 'workspace_memberships',
        'invitations' => 'workspace_invitations',
    ],

    /*
    |--------------------------------------------------------------------------
    | Soft Deletes
    |--------------------------------------------------------------------------
    |
    | Enable soft deletes for workspaces. When enabled, workspaces will be
    | soft deleted instead of permanently removed from the database.
    |
    */
    'soft_deletes' => true,

    /*
    |--------------------------------------------------------------------------
    | Global Scope Behavior
    |--------------------------------------------------------------------------
    |
    | Configure how the workspace global scope behaves.
    |
    */
    'scope' => [
        /*
        | When true, throw NoCurrentWorkspaceException if no workspace context is set.
        | When false, queries will return empty results (safer default).
        */
        'throw_when_missing' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Context Resolution
    |--------------------------------------------------------------------------
    |
    | How to determine the current workspace. Resolvers are tried in order
    | until one returns a workspace.
    |
    | Available resolvers:
    | - AuthUserResolver: From authenticated user's current_workspace_id
    | - SubdomainResolver: From request subdomain
    | - RouteParameterResolver: From route parameter
    | - HeaderResolver: From X-Workspace-Id header
    | - SessionResolver: From session
    |
    */
    'context' => [
        'resolvers' => [
            \Climactic\Workspaces\ContextResolvers\AuthUserResolver::class,
        ],

        // For subdomain resolver
        'subdomain' => [
            'domain' => env('WORKSPACES_DOMAIN', env('APP_DOMAIN')),
        ],

        // For route parameter resolver
        'route_parameter' => [
            'name' => 'workspace',
            'field' => 'slug', // or 'id', 'uuid'
        ],

        // For header resolver
        'header' => [
            'name' => 'X-Workspace-Id',
        ],

        // For session resolver
        'session' => [
            'key' => 'current_workspace_id',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Permissions Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how permissions are handled using the config-based provider.
    | You can implement your own PermissionProviderContract if needed.
    |
    */
    'permissions' => [
        /*
        | Permission provider to use. Set to null for the default ConfigPermissionProvider.
        | You can create custom providers by implementing PermissionProviderContract.
        */
        'provider' => null,

        /*
        | Available permissions in the system.
        | Permissions support wildcards: 'workspace.*' matches 'workspace.view', etc.
        */
        'available' => [
            // Workspace permissions
            'workspace.view',
            'workspace.update',
            'workspace.delete',

            // Member permissions
            'members.view',
            'members.invite',
            'members.remove',
            'members.update-role',

            // Invitation permissions
            'invitations.view',
            'invitations.create',
            'invitations.cancel',

            // Settings permissions
            'settings.view',
            'settings.update',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Roles
    |--------------------------------------------------------------------------
    |
    | Available workspace roles with their permissions.
    | Permissions are checked against this configuration.
    |
    | Permission wildcards:
    | - '*' grants all permissions
    | - 'workspace.*' grants all workspace.* permissions
    |
    */
    'roles' => [
        'owner' => [
            'name' => 'Owner',
            'description' => 'Full access to the workspace',
            'permissions' => ['*'],
        ],
        'admin' => [
            'name' => 'Administrator',
            'description' => 'Can manage members and settings',
            'permissions' => [
                'workspace.view',
                'workspace.update',
                'members.*',
                'invitations.*',
                'settings.*',
            ],
        ],
        'member' => [
            'name' => 'Member',
            'description' => 'Standard workspace access',
            'permissions' => [
                'workspace.view',
                'members.view',
                'invitations.view',
                'settings.view',
            ],
        ],
        'guest' => [
            'name' => 'Guest',
            'description' => 'Read-only access',
            'permissions' => [
                'workspace.view',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Role
    |--------------------------------------------------------------------------
    |
    | Role assigned to new members (via invitation or direct add).
    |
    */
    'default_role' => 'member',

    /*
    |--------------------------------------------------------------------------
    | Owner Role
    |--------------------------------------------------------------------------
    |
    | Role that signifies workspace ownership.
    |
    */
    'owner_role' => 'owner',

    /*
    |--------------------------------------------------------------------------
    | Invitations
    |--------------------------------------------------------------------------
    |
    | Configuration for the workspace invitation system.
    |
    */
    'invitations' => [
        'enabled' => true,
        'expires_after_days' => 7,
        'notification' => \Climactic\Workspaces\Notifications\WorkspaceInvitationNotification::class,
        'acceptance_url' => '/invitations/{token}/accept',
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-Create Workspace on Registration
    |--------------------------------------------------------------------------
    |
    | Automatically create a workspace when a user registers.
    |
    */
    'auto_create_on_registration' => [
        'enabled' => true,
        'name_from' => 'name', // User attribute to use for workspace name
        'name_suffix' => "'s Workspace", // e.g., "John's Workspace"
        'listen_to' => \Illuminate\Auth\Events\Registered::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Context Container Key
    |--------------------------------------------------------------------------
    |
    | Key used to bind current workspace in the container and Context facade.
    |
    */
    'container_key' => 'currentWorkspace',
    'context_key' => 'workspace_id',

    /*
    |--------------------------------------------------------------------------
    | Actions
    |--------------------------------------------------------------------------
    |
    | Customize actions for business logic. Replace with your own classes
    | to modify the default behavior.
    |
    */
    'actions' => [
        'create_workspace' => \Climactic\Workspaces\Actions\CreateWorkspace::class,
        'delete_workspace' => \Climactic\Workspaces\Actions\DeleteWorkspace::class,
        'add_member' => \Climactic\Workspaces\Actions\AddWorkspaceMember::class,
        'remove_member' => \Climactic\Workspaces\Actions\RemoveWorkspaceMember::class,
        'update_member_role' => \Climactic\Workspaces\Actions\UpdateMemberRole::class,
        'transfer_ownership' => \Climactic\Workspaces\Actions\TransferOwnership::class,
        'create_invitation' => \Climactic\Workspaces\Actions\CreateInvitation::class,
        'accept_invitation' => \Climactic\Workspaces\Actions\AcceptInvitation::class,
        'decline_invitation' => \Climactic\Workspaces\Actions\DeclineInvitation::class,
        'cancel_invitation' => \Climactic\Workspaces\Actions\CancelInvitation::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Middleware
    |--------------------------------------------------------------------------
    |
    | Middleware classes used by the package.
    |
    */
    'middleware' => [
        'set_context' => \Climactic\Workspaces\Middleware\SetWorkspaceContext::class,
        'ensure_access' => \Climactic\Workspaces\Middleware\EnsureWorkspaceAccess::class,
        'ensure_role' => \Climactic\Workspaces\Middleware\EnsureWorkspaceRole::class,
    ],
];
