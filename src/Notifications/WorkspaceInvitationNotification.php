<?php

declare(strict_types=1);

namespace Climactic\Workspaces\Notifications;

use Climactic\Workspaces\Contracts\WorkspaceInvitationContract;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WorkspaceInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public WorkspaceInvitationContract&Model $invitation
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        /** @var Model $workspace */
        $workspace = $this->invitation->workspace()->first();
        /** @var Model|null $inviter */
        $inviter = $this->invitation->inviter()->first();
        $roleName = $this->invitation->getRoleName();
        $acceptUrl = $this->invitation->getAcceptanceUrl();
        /** @var \Illuminate\Support\Carbon $expiresAt */
        $expiresAt = $this->invitation->getAttribute('expires_at');

        $inviterName = $inviter?->getAttribute('name') ?? 'Someone';
        $workspaceName = $workspace->getAttribute('name');

        return (new MailMessage)
            ->subject("You've been invited to join {$workspaceName}")
            ->greeting('Hello!')
            ->line("{$inviterName} has invited you to join **{$workspaceName}** as a **{$roleName}**.")
            ->action('Accept Invitation', $acceptUrl)
            ->line('This invitation will expire on '.$expiresAt->format('F j, Y').'.')
            ->line('If you did not expect this invitation, you can ignore this email.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        /** @var Model $workspace */
        $workspace = $this->invitation->workspace()->first();
        /** @var \Illuminate\Support\Carbon $expiresAt */
        $expiresAt = $this->invitation->getAttribute('expires_at');

        return [
            'invitation_id' => $this->invitation->getKey(),
            'workspace_id' => $this->invitation->getAttribute('workspace_id'),
            'workspace_name' => $workspace->getAttribute('name'),
            'role' => $this->invitation->getAttribute('role'),
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }
}
