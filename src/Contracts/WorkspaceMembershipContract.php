<?php

declare(strict_types=1);

namespace Climactic\Workspaces\Contracts;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

interface WorkspaceMembershipContract
{
    /**
     * Get the workspace this membership belongs to.
     */
    public function workspace(): BelongsTo;

    /**
     * Get the user this membership belongs to.
     */
    public function user(): BelongsTo;

    /**
     * Check if this membership has owner role.
     */
    public function isOwner(): bool;

    /**
     * Check if this membership has admin role.
     */
    public function isAdmin(): bool;

    /**
     * Check if this membership has member role.
     */
    public function isMember(): bool;

    /**
     * Check if this membership has a specific role.
     */
    public function hasRole(string $role): bool;

    /**
     * Check if this membership has a specific permission.
     */
    public function hasPermission(string $permission): bool;

    /**
     * Get all permissions for this membership.
     */
    public function getPermissions(): array;
}
