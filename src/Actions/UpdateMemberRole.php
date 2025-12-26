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
        // The updateMemberRole method on the workspace handles the event
        $workspace->updateMemberRole($user, $role);
    }
}
