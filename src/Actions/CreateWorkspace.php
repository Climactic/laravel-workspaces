<?php

declare(strict_types=1);

namespace Climactic\Workspaces\Actions;

use Climactic\Workspaces\Contracts\WorkspaceContract;
use Climactic\Workspaces\Events\WorkspaceCreated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateWorkspace
{
    /**
     * Create a new workspace.
     *
     * @param  array<string, mixed>  $data
     * @param  Model|string|int|null  $owner  The owner of the workspace
     * @param  bool  $setAsCurrent  Whether to set this as the owner's current workspace
     */
    public function execute(
        array $data,
        Model|string|int|null $owner = null,
        bool $setAsCurrent = false
    ): WorkspaceContract {
        $workspaceModel = config('workspaces.models.workspace');
        $ownerRole = config('workspaces.owner_role', 'owner');

        return DB::transaction(function () use ($workspaceModel, $data, $owner, $ownerRole, $setAsCurrent) {
            // Set owner_id if owner is provided
            if ($owner) {
                $data['owner_id'] = $owner instanceof Model ? $owner->getKey() : $owner;
            }

            /** @var WorkspaceContract $workspace */
            $workspace = $workspaceModel::create($data);

            // Add owner as a member with owner role
            if ($owner) {
                $workspace->addMember($owner, $ownerRole, $setAsCurrent);
            }

            event(new WorkspaceCreated($workspace, $owner));

            return $workspace;
        });
    }
}
