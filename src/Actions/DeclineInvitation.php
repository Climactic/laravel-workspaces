<?php

declare(strict_types=1);

namespace Climactic\Workspaces\Actions;

use Climactic\Workspaces\Contracts\WorkspaceInvitationContract;
use Climactic\Workspaces\Events\InvitationDeclined;
use Climactic\Workspaces\Exceptions\InvitationAlreadyAcceptedException;
use Illuminate\Database\Eloquent\Model;

class DeclineInvitation
{
    /**
     * Decline a workspace invitation.
     */
    public function execute(WorkspaceInvitationContract|Model|string $invitation): void
    {
        // Resolve invitation if token is provided
        if (is_string($invitation)) {
            $invitationModel = config('workspaces.models.invitation');
            $invitation = $invitationModel::where('token', $invitation)->firstOrFail();
        }

        // Check if already accepted
        if ($invitation->isAccepted()) {
            throw new InvitationAlreadyAcceptedException(
                'This invitation has already been accepted and cannot be declined.'
            );
        }

        // Check if already declined
        if ($invitation->isDeclined()) {
            return; // Already declined, no action needed
        }

        // Mark invitation as declined
        $invitation->markAsDeclined();

        event(new InvitationDeclined($invitation));
    }
}
