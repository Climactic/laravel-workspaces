<?php

declare(strict_types=1);

namespace Climactic\Workspaces\Actions;

use Climactic\Workspaces\Contracts\WorkspaceContract;
use Climactic\Workspaces\Contracts\WorkspaceInvitationContract;
use Climactic\Workspaces\Events\InvitationCreated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Notification;

class CreateInvitation
{
    /**
     * Create a new workspace invitation.
     */
    public function execute(
        WorkspaceContract|Model $workspace,
        string $email,
        ?string $role = null,
        Model|string|int|null $invitedBy = null
    ): WorkspaceInvitationContract {
        $invitationModel = config('workspaces.models.invitation');
        $role = $role ?? config('workspaces.default_role', 'member');
        $expiresAfterDays = config('workspaces.invitations.expires_after_days', 7);

        // Check if a pending invitation already exists
        $existing = $invitationModel::where('workspace_id', $workspace->getKey())
            ->where('email', $email)
            ->pending()
            ->first();

        if ($existing) {
            return $existing;
        }

        // Get inviter ID
        $inviterId = null;
        if ($invitedBy) {
            $inviterId = $invitedBy instanceof Model ? $invitedBy->getKey() : $invitedBy;
        }

        /** @var WorkspaceInvitationContract $invitation */
        $invitation = $invitationModel::create([
            'workspace_id' => $workspace->getKey(),
            'email' => $email,
            'role' => $role,
            'token' => $invitationModel::generateToken(),
            'invited_by' => $inviterId,
            'expires_at' => now()->addDays($expiresAfterDays),
        ]);

        event(new InvitationCreated($invitation));

        // Send notification if configured
        $notificationClass = config('workspaces.invitations.notification');
        if ($notificationClass && class_exists($notificationClass)) {
            Notification::route('mail', $email)
                ->notify(new $notificationClass($invitation));
        }

        return $invitation;
    }
}
