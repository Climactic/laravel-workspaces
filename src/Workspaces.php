<?php

declare(strict_types=1);

namespace Climactic\Workspaces;

use Climactic\Workspaces\Contracts\WorkspaceContract;
use Climactic\Workspaces\Contracts\WorkspaceInvitationContract;
use Illuminate\Database\Eloquent\Model;

class Workspaces
{
    /**
     * Get the current workspace.
     *
     * @return (WorkspaceContract&Model)|null
     */
    public function current(): ?WorkspaceContract
    {
        $containerKey = config('workspaces.container_key', 'currentWorkspace');

        return app($containerKey);
    }

    /**
     * Check if there is a current workspace.
     */
    public function check(): bool
    {
        return $this->current() !== null;
    }

    /**
     * Get the current workspace ID.
     */
    public function id(): mixed
    {
        return $this->current()?->getKey();
    }

    /**
     * Get the workspace model class.
     */
    public function workspaceModel(): string
    {
        return config('workspaces.models.workspace');
    }

    /**
     * Get the membership model class.
     */
    public function membershipModel(): string
    {
        return config('workspaces.models.membership');
    }

    /**
     * Get the invitation model class.
     */
    public function invitationModel(): string
    {
        return config('workspaces.models.invitation');
    }

    /**
     * Create a new workspace.
     *
     * @param  array<string, mixed>  $data
     */
    public function createWorkspace(array $data, Model|string|int|null $owner = null): WorkspaceContract
    {
        $action = app(config('workspaces.actions.create_workspace'));

        return $action->execute($data, $owner);
    }

    /**
     * Delete a workspace.
     */
    public function deleteWorkspace(WorkspaceContract|Model|string|int $workspace, bool $force = false): bool
    {
        $action = app(config('workspaces.actions.delete_workspace'));

        return $action->execute($workspace, $force);
    }

    /**
     * Add a member to a workspace.
     */
    public function addMember(
        WorkspaceContract|Model $workspace,
        Model|string|int $user,
        ?string $role = null
    ): void {
        $action = app(config('workspaces.actions.add_member'));
        $action->execute($workspace, $user, $role);
    }

    /**
     * Remove a member from a workspace.
     */
    public function removeMember(WorkspaceContract|Model $workspace, Model|string|int $user): void
    {
        $action = app(config('workspaces.actions.remove_member'));
        $action->execute($workspace, $user);
    }

    /**
     * Update a member's role.
     */
    public function updateMemberRole(
        WorkspaceContract|Model $workspace,
        Model|string|int $user,
        string $role
    ): void {
        $action = app(config('workspaces.actions.update_member_role'));
        $action->execute($workspace, $user, $role);
    }

    /**
     * Create an invitation.
     */
    public function invite(
        WorkspaceContract|Model $workspace,
        string $email,
        ?string $role = null,
        Model|string|int|null $invitedBy = null
    ): WorkspaceInvitationContract {
        $action = app(config('workspaces.actions.create_invitation'));

        return $action->execute($workspace, $email, $role, $invitedBy);
    }

    /**
     * Accept an invitation.
     */
    public function acceptInvitation(
        WorkspaceInvitationContract|Model|string $invitation,
        Model $user
    ): WorkspaceContract {
        $action = app(config('workspaces.actions.accept_invitation'));

        return $action->execute($invitation, $user);
    }

    /**
     * Decline an invitation.
     */
    public function declineInvitation(WorkspaceInvitationContract|Model|string $invitation): void
    {
        $action = app(config('workspaces.actions.decline_invitation'));
        $action->execute($invitation);
    }

    /**
     * Cancel an invitation.
     */
    public function cancelInvitation(WorkspaceInvitationContract|Model|string $invitation): void
    {
        $action = app(config('workspaces.actions.cancel_invitation'));
        $action->execute($invitation);
    }

    /**
     * Find an invitation by token.
     */
    public function findInvitationByToken(string $token): ?WorkspaceInvitationContract
    {
        return $this->invitationModel()::where('token', $token)->first();
    }

    /**
     * Get all available roles.
     *
     * @return array<string, array<string, mixed>>
     */
    public function roles(): array
    {
        return config('workspaces.roles', []);
    }

    /**
     * Get role names as key-value pairs.
     *
     * @return array<string, string>
     */
    public function roleNames(): array
    {
        return collect($this->roles())
            ->mapWithKeys(fn ($role, $key) => [$key => $role['name']])
            ->toArray();
    }

    /**
     * Get the owner role key.
     */
    public function ownerRole(): string
    {
        return config('workspaces.owner_role', 'owner');
    }

    /**
     * Get the default role key.
     */
    public function defaultRole(): string
    {
        return config('workspaces.default_role', 'member');
    }

    /**
     * Find a workspace by ID.
     */
    public function find(string|int $id): ?WorkspaceContract
    {
        return $this->workspaceModel()::find($id);
    }

    /**
     * Find a workspace by slug.
     */
    public function findBySlug(string $slug): ?WorkspaceContract
    {
        return $this->workspaceModel()::where('slug', $slug)->first();
    }

    /**
     * Execute a callback in the context of a workspace.
     *
     * @param  WorkspaceContract&Model  $workspace
     */
    public function runAs(WorkspaceContract&Model $workspace, callable $callback): mixed
    {
        return $workspace->execute($callback);
    }
}
