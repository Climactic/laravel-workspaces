<?php

declare(strict_types=1);

namespace Climactic\Workspaces\Actions;

use Climactic\Workspaces\Contracts\WorkspaceContract;
use Climactic\Workspaces\Events\WorkspaceDeleted;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class DeleteWorkspace
{
    /**
     * Delete a workspace.
     */
    public function execute(WorkspaceContract|Model|string|int $workspace, bool $force = false): bool
    {
        $workspaceModel = config('workspaces.models.workspace');

        // Resolve workspace if not already a model
        if (! $workspace instanceof WorkspaceContract) {
            $workspace = $workspaceModel::find($workspace);
        }

        if (! $workspace) {
            return false;
        }

        return DB::transaction(function () use ($workspace, $force) {
            // Fire event before deletion
            event(new WorkspaceDeleted($workspace));

            // Delete (soft or hard based on config and force flag)
            if ($force) {
                $workspace->forceDelete();
            } else {
                $workspace->delete();
            }

            return true;
        });
    }
}
