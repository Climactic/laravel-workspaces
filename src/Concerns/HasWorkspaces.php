<?php

declare(strict_types=1);

namespace Climactic\Workspaces\Concerns;

use Climactic\Workspaces\Contracts\WorkspaceContract;
use Climactic\Workspaces\Events\WorkspaceSwitched;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Trait to be used on the User model.
 *
 * Provides workspace relationships and helper methods.
 * Uses the pivot table (workspace_memberships) to track the current workspace
 * via the is_current column, avoiding modifications to the users table.
 */
trait HasWorkspaces
{
    /**
     * Get the user's current workspace membership.
     */
    public function currentWorkspaceMembership(): HasOne
    {
        return $this->hasOne(
            config('workspaces.models.membership'),
            'user_id'
        )->where('is_current', true);
    }

    /**
     * Get the user's current workspace (accessor for $user->currentWorkspace).
     */
    public function getCurrentWorkspaceAttribute(): ?WorkspaceContract
    {
        $membership = $this->currentWorkspaceMembership;

        return $membership?->workspace;
    }

    /**
     * Get the user's current workspace.
     *
     * Alias method for getCurrentWorkspaceAttribute.
     */
    public function currentWorkspace(): ?WorkspaceContract
    {
        return $this->getCurrentWorkspaceAttribute();
    }

    /**
     * Get the current workspace ID.
     */
    public function getCurrentWorkspaceIdAttribute(): mixed
    {
        return $this->currentWorkspaceMembership?->workspace_id;
    }

    /**
     * Get workspaces owned by this user.
     */
    public function ownedWorkspaces(): HasMany
    {
        return $this->hasMany(
            config('workspaces.models.workspace'),
            'owner_id'
        );
    }

    /**
     * Get all workspace memberships for this user.
     */
    public function workspaceMemberships(): HasMany
    {
        return $this->hasMany(
            config('workspaces.models.membership'),
            'user_id'
        );
    }

    /**
     * Get all workspaces this user belongs to.
     */
    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(
            config('workspaces.models.workspace'),
            config('workspaces.tables.memberships', 'workspace_memberships'),
            'user_id',
            'workspace_id'
        )->withPivot(['role', 'permissions', 'is_current', 'joined_at'])
            ->withTimestamps();
    }

    /**
     * Check if user belongs to a workspace.
     */
    public function belongsToWorkspace(Model|string|int $workspace): bool
    {
        $workspaceId = $workspace instanceof Model ? $workspace->getKey() : $workspace;
        $workspaceTable = config('workspaces.tables.workspaces', 'workspaces');

        // Use direct query to avoid stale cache issues
        return $this->workspaces()->where(
            "{$workspaceTable}.id",
            $workspaceId
        )->exists();
    }

    /**
     * Check if user owns a workspace.
     */
    public function ownsWorkspace(Model|string|int $workspace): bool
    {
        $workspaceId = $workspace instanceof Model ? $workspace->getKey() : $workspace;

        return $this->ownedWorkspaces->contains(fn ($w) => $w->getKey() === $workspaceId);
    }

    /**
     * Get user's role in a workspace.
     */
    public function workspaceRole(Model|string|int $workspace): ?string
    {
        $workspaceId = $workspace instanceof Model ? $workspace->getKey() : $workspace;
        $workspaceTable = config('workspaces.tables.workspaces', 'workspaces');

        // Use direct query to avoid loading all workspaces into memory
        return $this->workspaces()
            ->where("{$workspaceTable}.id", $workspaceId)
            ->first()
            ?->pivot
            ?->role;
    }

    /**
     * Check if user has a specific role in a workspace.
     */
    public function hasWorkspaceRole(Model|string|int $workspace, string|array $roles): bool
    {
        $userRole = $this->workspaceRole($workspace);

        if ($userRole === null) {
            return false;
        }

        return in_array($userRole, (array) $roles, true);
    }

    /**
     * Check if user has a specific permission in a workspace.
     *
     * Uses the configured permission provider.
     */
    public function hasWorkspacePermission(Model|string|int $workspace, string $permission): bool
    {
        $workspaceModel = $workspace instanceof Model
            ? $workspace
            : config('workspaces.models.workspace')::find($workspace);

        if (! $workspaceModel) {
            return false;
        }

        return app(\Climactic\Workspaces\Permissions\PermissionManager::class)
            ->hasPermission($this, $workspaceModel, $permission);
    }

    /**
     * Get all permissions for this user in a workspace.
     */
    public function workspacePermissions(Model|string|int $workspace): array
    {
        $workspaceModel = $workspace instanceof Model
            ? $workspace
            : config('workspaces.models.workspace')::find($workspace);

        if (! $workspaceModel) {
            return [];
        }

        return app(\Climactic\Workspaces\Permissions\PermissionManager::class)
            ->getPermissions($this, $workspaceModel);
    }

    /**
     * Switch user's current workspace.
     */
    public function switchWorkspace(Model|string|int $workspace): bool
    {
        $workspaceId = $workspace instanceof Model ? $workspace->getKey() : $workspace;

        if (! $this->belongsToWorkspace($workspace)) {
            return false;
        }

        $membershipModel = config('workspaces.models.membership');
        $oldWorkspaceId = $this->current_workspace_id;

        // Clear current flag from all memberships for this user
        $membershipModel::where('user_id', $this->getKey())
            ->where('is_current', true)
            ->update(['is_current' => false]);

        // Set current flag on the target workspace membership
        $membershipModel::where('user_id', $this->getKey())
            ->where('workspace_id', $workspaceId)
            ->update(['is_current' => true]);

        // Clear cached relationships
        $this->unsetRelation('currentWorkspaceMembership');
        $this->unsetRelation('workspaces');

        event(new WorkspaceSwitched($this, $workspace, $oldWorkspaceId));

        return true;
    }

    /**
     * Check if a workspace is the user's current workspace.
     */
    public function isCurrentWorkspace(Model|string|int $workspace): bool
    {
        $workspaceId = $workspace instanceof Model ? $workspace->getKey() : $workspace;

        return $this->current_workspace_id === $workspaceId;
    }

    /**
     * Get the user's personal workspace (if any).
     */
    public function personalWorkspace(): ?Model
    {
        return $this->ownedWorkspaces->first(fn ($w) => $w->personal);
    }

    /**
     * Check if user is owner of a workspace.
     */
    public function isWorkspaceOwner(Model|string|int $workspace): bool
    {
        return $this->hasWorkspaceRole($workspace, config('workspaces.owner_role', 'owner'));
    }

    /**
     * Check if user is admin of a workspace.
     */
    public function isWorkspaceAdmin(Model|string|int $workspace): bool
    {
        return $this->hasWorkspaceRole($workspace, ['owner', 'admin']);
    }

    /**
     * Leave a workspace.
     *
     * @throws \InvalidArgumentException If user is the owner
     */
    public function leaveWorkspace(Model|string|int $workspace): bool
    {
        if (! $this->belongsToWorkspace($workspace)) {
            return false;
        }

        // Get the workspace model if we received an ID
        $workspaceModel = $workspace instanceof Model
            ? $workspace
            : config('workspaces.models.workspace')::find($workspace);

        if (! $workspaceModel) {
            return false;
        }

        // Prevent owner from leaving
        if ($this->isWorkspaceOwner($workspace)) {
            throw new \InvalidArgumentException(
                'Owners cannot leave their workspace. Transfer ownership or delete the workspace first.'
            );
        }

        $workspaceModel->removeMember($this);

        return true;
    }
}
