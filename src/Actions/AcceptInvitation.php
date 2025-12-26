<?php

declare(strict_types=1);

namespace Climactic\Workspaces\Actions;

use Climactic\Workspaces\Contracts\WorkspaceContract;
use Climactic\Workspaces\Contracts\WorkspaceInvitationContract;
use Climactic\Workspaces\Events\InvitationAccepted;
use Climactic\Workspaces\Exceptions\InvitationAlreadyAcceptedException;
use Climactic\Workspaces\Exceptions\InvitationEmailMismatchException;
use Climactic\Workspaces\Exceptions\InvitationExpiredException;
use Illuminate\Database\Eloquent\Model;

class AcceptInvitation
{
    /**
     * Accept a workspace invitation.
     *
     * @param  WorkspaceInvitationContract|Model|string  $invitation
     * @param  Model  $user  User model with HasWorkspaces trait
     */
    public function execute(
        WorkspaceInvitationContract|Model|string $invitation,
        Model $user
    ): WorkspaceContract {
        // Resolve invitation if token is provided
        if (is_string($invitation)) {
            $invitationModel = config('workspaces.models.invitation');
            $invitation = $invitationModel::where('token', $invitation)->firstOrFail();
        }

        // Check if already accepted
        if ($invitation->isAccepted()) {
            throw new InvitationAlreadyAcceptedException(
                'This invitation has already been accepted.'
            );
        }

        // Check if declined
        if ($invitation->isDeclined()) {
            throw new InvitationAlreadyAcceptedException(
                'This invitation has been declined and cannot be accepted.'
            );
        }

        // Check if expired
        if ($invitation->isExpired()) {
            throw new InvitationExpiredException(
                'This invitation has expired.'
            );
        }

        // Verify user's email matches invitation email
        /** @var string|null $userEmail */
        $userEmail = $user->getAttribute('email');
        /** @var string $invitationEmail */
        $invitationEmail = $invitation->getAttribute('email');

        if ($userEmail === null || mb_strtolower($userEmail) !== mb_strtolower($invitationEmail)) {
            throw new InvitationEmailMismatchException(
                'This invitation was sent to a different email address.'
            );
        }

        $workspace = $invitation->workspace;

        // Add user to workspace if not already a member
        if (! $workspace->hasUser($user)) {
            $workspace->addMember($user, $invitation->role);
        }

        // Mark invitation as accepted
        $invitation->markAsAccepted();

        // Switch user to this workspace if they don't have a current one
        if (method_exists($user, 'currentWorkspace') && method_exists($user, 'switchWorkspace') && ! $user->currentWorkspace()) {
            $user->switchWorkspace($workspace);
        }

        event(new InvitationAccepted($invitation, $user));

        return $workspace;
    }
}
