<?php

declare(strict_types=1);

namespace Climactic\Workspaces\Permissions;

use Climactic\Workspaces\Contracts\PermissionProviderContract;
use Illuminate\Database\Eloquent\Model;

/**
 * Permission Manager.
 *
 * Manages permission checking across the package.
 */
class PermissionManager implements PermissionProviderContract
{
    protected PermissionProviderContract $provider;

    public function __construct()
    {
        $this->provider = $this->resolveProvider();
    }

    /**
     * Resolve the permission provider.
     */
    protected function resolveProvider(): PermissionProviderContract
    {
        $configuredProvider = config('workspaces.permissions.provider');

        // If explicitly configured, use that
        if ($configuredProvider) {
            return app($configuredProvider);
        }

        // Default to config-based provider
        return new ConfigPermissionProvider;
    }

    /**
     * Get the active provider instance.
     */
    public function getProvider(): PermissionProviderContract
    {
        return $this->provider;
    }

    /**
     * Check if a user has a specific permission in a workspace.
     */
    public function hasPermission(Model $user, Model $workspace, string $permission): bool
    {
        return $this->provider->hasPermission($user, $workspace, $permission);
    }

    /**
     * Check if a user has a specific role in a workspace.
     */
    public function hasRole(Model $user, Model $workspace, string|array $roles): bool
    {
        return $this->provider->hasRole($user, $workspace, $roles);
    }

    /**
     * Get all permissions for a user in a workspace.
     */
    public function getPermissions(Model $user, Model $workspace): array
    {
        return $this->provider->getPermissions($user, $workspace);
    }

    /**
     * Get the user's role in a workspace.
     */
    public function getRole(Model $user, Model $workspace): ?string
    {
        return $this->provider->getRole($user, $workspace);
    }

    /**
     * Assign a role to a user in a workspace.
     */
    public function assignRole(Model $user, Model $workspace, string $role): void
    {
        $this->provider->assignRole($user, $workspace, $role);
    }

    /**
     * Remove a role from a user in a workspace.
     */
    public function removeRole(Model $user, Model $workspace): void
    {
        $this->provider->removeRole($user, $workspace);
    }

    /**
     * Set the workspace context for permission checking.
     */
    public function setWorkspaceContext(Model $workspace): void
    {
        $this->provider->setWorkspaceContext($workspace);
    }

    /**
     * Get all available roles.
     */
    public function getAvailableRoles(): array
    {
        return $this->provider->getAvailableRoles();
    }

    /**
     * Get all available permissions.
     */
    public function getAvailablePermissions(): array
    {
        return $this->provider->getAvailablePermissions();
    }
}
