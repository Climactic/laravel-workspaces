<?php

declare(strict_types=1);

namespace Climactic\Workspaces\Actions;

use Climactic\Workspaces\Contracts\WorkspaceContract;
use Illuminate\Database\Eloquent\Model;

class AddWorkspaceMember
{
    /**
     * Add a member to a workspace.
     *
     * @param  WorkspaceContract|Model  $workspace  The workspace to add the member to
     * @param  Model|string|int  $user  The user to add
     * @param  string|null  $role  The role to assign
     * @param  bool  $setAsCurrent  Whether to set this as the user's current workspace
     */
    public function execute(
        WorkspaceContract|Model $workspace,
        Model|string|int $user,
        ?string $role = null,
        bool $setAsCurrent = false
    ): void {
        $role = $role ?? config('workspaces.default_role', 'member');

        // The addMember method on the workspace handles the event
        $workspace->addMember($user, $role, $setAsCurrent);
    }
}
