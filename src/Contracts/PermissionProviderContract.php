<?php

declare(strict_types=1);

namespace Climactic\Workspaces\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Contract for permission providers.
 *
 * Allows the package to work with different permission systems:
 * - Config-based (default, no dependencies)
 * - Custom implementations
 */
interface PermissionProviderContract
{
    /**
     * Check if a user has a specific permission in a workspace.
     */
    public function hasPermission(Model $user, Model $workspace, string $permission): bool;

    /**
     * Check if a user has a specific role in a workspace.
     */
    public function hasRole(Model $user, Model $workspace, string|array $roles): bool;

    /**
     * Get all permissions for a user in a workspace.
     */
    public function getPermissions(Model $user, Model $workspace): array;

    /**
     * Get the user's role in a workspace.
     */
    public function getRole(Model $user, Model $workspace): ?string;

    /**
     * Assign a role to a user in a workspace.
     */
    public function assignRole(Model $user, Model $workspace, string $role): void;

    /**
     * Remove a role from a user in a workspace.
     */
    public function removeRole(Model $user, Model $workspace): void;

    /**
     * Set the workspace context for permission checking.
     */
    public function setWorkspaceContext(Model $workspace): void;

    /**
     * Get all available roles.
     */
    public function getAvailableRoles(): array;

    /**
     * Get all available permissions.
     */
    public function getAvailablePermissions(): array;
}
