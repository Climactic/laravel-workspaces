<?php

declare(strict_types=1);

namespace Climactic\Workspaces\Actions;

use Climactic\Workspaces\Contracts\WorkspaceContract;
use Illuminate\Database\Eloquent\Model;

class RemoveWorkspaceMember
{
    /**
     * Remove a member from a workspace.
     */
    public function execute(WorkspaceContract|Model $workspace, Model|string|int $user): void
    {
        // Clear user's current workspace if it's this one
        $userId = $user instanceof Model ? $user->getKey() : $user;
        $userModel = $user instanceof Model ? $user : config('workspaces.user_model')::find($userId);

        if ($userModel && $userModel->current_workspace_id === $workspace->getKey()) {
            $userModel->update(['current_workspace_id' => null]);
        }

        // The removeMember method on the workspace handles the event
        $workspace->removeMember($user);
    }
}
