<?php

declare(strict_types=1);

namespace Climactic\Workspaces\Actions;

use Climactic\Workspaces\Contracts\WorkspaceContract;
use Climactic\Workspaces\Events\OwnershipTransferred;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TransferOwnership
{
    /**
     * Transfer ownership of a workspace to a new user.
     *
     * @param  WorkspaceContract&Model  $workspace  The workspace to transfer
     * @param  Model|string|int  $newOwner  The new owner (must be a member)
     * @param  Model|null  $performedBy  The user performing the transfer (must be current owner)
     * @param  bool  $demotePreviousOwner  Whether to demote the previous owner to admin
     *
     * @throws InvalidArgumentException If the new owner is not a member or performer is not current owner
     */
    public function execute(
        WorkspaceContract&Model $workspace,
        Model|string|int $newOwner,
        ?Model $performedBy = null,
        bool $demotePreviousOwner = true
    ): void {
        $newOwnerId = $newOwner instanceof Model ? $newOwner->getKey() : $newOwner;
        $ownerRole = config('workspaces.owner_role', 'owner');
        $adminRole = 'admin';

        /** @var Model|null $previousOwner */
        $previousOwner = $workspace->owner()->first();

        // Verify performer is the current owner (if provided)
        if ($performedBy !== null && $previousOwner !== null) {
            if ($performedBy->getKey() !== $previousOwner->getKey()) {
                throw new InvalidArgumentException('Only the current owner can transfer ownership.');
            }
        }

        // Verify new owner is a member
        if (! $workspace->hasUser($newOwner)) {
            throw new InvalidArgumentException('The new owner must be a member of the workspace.');
        }

        DB::transaction(function () use ($workspace, $newOwnerId, $previousOwner, $ownerRole, $adminRole, $demotePreviousOwner): void {
            // Update the workspace owner_id
            $workspace->setAttribute('owner_id', $newOwnerId);
            $workspace->save();

            // Update the new owner's role to owner
            $workspace->updateMemberRole($newOwnerId, $ownerRole);

            // Optionally demote the previous owner to admin
            if ($demotePreviousOwner && $previousOwner instanceof Model && $workspace->hasUser($previousOwner)) {
                $workspace->updateMemberRole($previousOwner, $adminRole);
            }
        });

        event(new OwnershipTransferred($workspace, $previousOwner, $newOwner));
    }
}
