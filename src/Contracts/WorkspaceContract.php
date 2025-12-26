<?php

declare(strict_types=1);

namespace Climactic\Workspaces\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

interface WorkspaceContract
{
    /**
     * Get the workspace owner.
     */
    public function owner(): BelongsTo;

    /**
     * Get the workspace memberships.
     */
    public function memberships(): HasMany;

    /**
     * Get the workspace members.
     */
    public function members(): BelongsToMany;

    /**
     * Get the workspace invitations.
     */
    public function invitations(): HasMany;

    /**
     * Get pending invitations.
     */
    public function pendingInvitations(): HasMany;

    /**
     * Check if a user is a member of this workspace.
     */
    public function hasUser(Model|string|int $user): bool;

    /**
     * Check if a user has a specific role in this workspace.
     */
    public function hasUserWithRole(Model|string|int $user, string $role): bool;

    /**
     * Get the role of a user in this workspace.
     */
    public function getMemberRole(Model|string|int $user): ?string;

    /**
     * Add a member to this workspace.
     *
     * @param  Model|string|int  $user  The user to add
     * @param  string|null  $role  The role to assign
     * @param  bool  $setAsCurrent  Whether to set this as the user's current workspace
     */
    public function addMember(Model|string|int $user, ?string $role = null, bool $setAsCurrent = false): void;

    /**
     * Remove a member from this workspace.
     */
    public function removeMember(Model|string|int $user): void;

    /**
     * Update a member's role in this workspace.
     */
    public function updateMemberRole(Model|string|int $user, string $role): void;

    /**
     * Check if this is a personal workspace.
     */
    public function isPersonal(): bool;

    /**
     * Make this workspace the current workspace.
     */
    public function makeCurrent(): static;

    /**
     * Forget the current workspace from the container.
     */
    public function forgetCurrent(): static;

    /**
     * Get the current workspace.
     */
    public static function current(): ?static;

    /**
     * Check if there is a current workspace.
     */
    public static function checkCurrent(): bool;

    /**
     * Execute a callback within the context of this workspace.
     */
    public function execute(callable $callback): mixed;

    /**
     * Get the primary key value.
     *
     * @return mixed
     */
    public function getKey();
}
