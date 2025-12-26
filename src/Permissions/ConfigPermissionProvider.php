<?php

declare(strict_types=1);

namespace Climactic\Workspaces\Permissions;

use Climactic\Workspaces\Contracts\PermissionProviderContract;
use Illuminate\Database\Eloquent\Model;

/**
 * Config-based permission provider.
 *
 * Uses the roles and permissions defined in config/workspaces.php.
 */
class ConfigPermissionProvider implements PermissionProviderContract
{
    /**
     * Check if a user has a specific permission in a workspace.
     */
    public function hasPermission(Model $user, Model $workspace, string $permission): bool
    {
        $role = $this->getRole($user, $workspace);

        if (! $role) {
            return false;
        }

        $roleConfig = config("workspaces.roles.{$role}");

        if (! $roleConfig) {
            return false;
        }

        $permissions = $roleConfig['permissions'] ?? [];

        // Check for wildcard permission
        if (in_array('*', $permissions, true)) {
            return true;
        }

        // Check for exact match
        if (in_array($permission, $permissions, true)) {
            return true;
        }

        // Check for wildcard patterns (e.g., "workspace.*" matches "workspace.view")
        foreach ($permissions as $pattern) {
            if ($this->matchesWildcard($pattern, $permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a permission pattern matches a given permission.
     */
    protected function matchesWildcard(string $pattern, string $permission): bool
    {
        if (! str_contains($pattern, '*')) {
            return false;
        }

        // First escape all regex special characters, then convert wildcards
        // This prevents injection of regex patterns via permission names
        $escaped = preg_quote($pattern, '/');

        // Now convert escaped wildcards (\*) back to regex wildcards (.*)
        $regex = '/^'.str_replace('\\*', '.*', $escaped).'$/';

        return (bool) preg_match($regex, $permission);
    }

    /**
     * Check if a user has a specific role in a workspace.
     */
    public function hasRole(Model $user, Model $workspace, string|array $roles): bool
    {
        $userRole = $this->getRole($user, $workspace);

        if ($userRole === null) {
            return false;
        }

        return in_array($userRole, (array) $roles, true);
    }

    /**
     * Get all permissions for a user in a workspace.
     */
    public function getPermissions(Model $user, Model $workspace): array
    {
        $role = $this->getRole($user, $workspace);

        if (! $role) {
            return [];
        }

        $roleConfig = config("workspaces.roles.{$role}");

        return $roleConfig['permissions'] ?? [];
    }

    /**
     * Get the user's role in a workspace.
     */
    public function getRole(Model $user, Model $workspace): ?string
    {
        if (! method_exists($user, 'workspaceRole')) {
            return null;
        }

        return $user->workspaceRole($workspace);
    }

    /**
     * Assign a role to a user in a workspace.
     *
     * For config-based provider, this updates the pivot table role.
     */
    public function assignRole(Model $user, Model $workspace, string $role): void
    {
        if (method_exists($workspace, 'updateMemberRole')) {
            $workspace->updateMemberRole($user, $role);
        }
    }

    /**
     * Remove a role from a user in a workspace.
     *
     * For config-based provider, this removes the user from the workspace.
     */
    public function removeRole(Model $user, Model $workspace): void
    {
        if (method_exists($workspace, 'removeMember')) {
            $workspace->removeMember($user);
        }
    }

    /**
     * Set workspace context (no-op for config provider).
     */
    public function setWorkspaceContext(Model $workspace): void
    {
        // No-op for config-based provider
    }

    /**
     * Get all available roles from config.
     */
    public function getAvailableRoles(): array
    {
        return array_keys(config('workspaces.roles', []));
    }

    /**
     * Get all available permissions from config.
     */
    public function getAvailablePermissions(): array
    {
        return config('workspaces.permissions.available', []);
    }
}
