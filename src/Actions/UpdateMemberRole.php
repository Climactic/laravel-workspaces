<?php

declare(strict_types=1);

namespace Climactic\Workspaces\Actions;

use Climactic\Workspaces\Contracts\WorkspaceContract;
use Illuminate\Database\Eloquent\Model;

class UpdateMemberRole
{
    /**
     * Update a member's role in a workspace.
     */
    public function execute(
        WorkspaceContract|Model $workspace,
        Model|string|int $user,
        string $role
    ): void {
        $ownerRole = config('workspaces.owner_role', 'owner');

        // Prevent assigning owner role via this action
        if ($role === $ownerRole) {
            throw new \InvalidArgumentException(
                'Cannot assign owner role via role update. Use ownership transfer instead.'
            );
        }

        // Validate role exists in config
        $validRoles = array_keys(config('workspaces.roles', []));
        if (! in_array($role, $validRoles, true)) {
            throw new \InvalidArgumentException(
                "Invalid role '{$role}'. Valid roles are: ".implode(', ', $validRoles)
            );
        }

        // The updateMemberRole method on the workspace handles the event
        $workspace->updateMemberRole($user, $role);
    }
}
