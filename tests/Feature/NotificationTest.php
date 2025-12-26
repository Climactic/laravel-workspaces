<?php

use Climactic\Workspaces\Models\WorkspaceInvitation;
use Climactic\Workspaces\Notifications\WorkspaceInvitationNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

describe('WorkspaceInvitationNotification', function () {
    it('can be sent via mail channel', function () {
        [$workspace, $owner] = createWorkspaceWithOwner(['name' => 'Test Team']);

        $invitation = WorkspaceInvitation::factory()
            ->forWorkspace($workspace)
            ->invitedBy($owner)
            ->create([
                'email' => 'invite@example.com',
                'role' => 'member',
                'expires_at' => now()->addDays(7),
            ]);

        $notification = new WorkspaceInvitationNotification($invitation);

        $channels = $notification->via(new AnonymousNotifiable);

        expect($channels)->toContain('mail');
    });

    it('generates proper mail message', function () {
        [$workspace, $owner] = createWorkspaceWithOwner(
            ['name' => 'Acme Corp'],
            ['name' => 'John Smith']
        );

        $invitation = WorkspaceInvitation::factory()
            ->forWorkspace($workspace)
            ->invitedBy($owner)
            ->create([
                'email' => 'newuser@example.com',
                'role' => 'admin',
                'expires_at' => now()->addDays(7),
            ]);

        $notification = new WorkspaceInvitationNotification($invitation);
        $mail = $notification->toMail(new AnonymousNotifiable);

        expect($mail->subject)->toBe("You've been invited to join Acme Corp")
            ->and($mail->greeting)->toBe('Hello!');

        // Check the mail contains expected content
        $mailContent = implode(' ', array_map(fn ($line) => is_string($line) ? $line : '', $mail->introLines));
        expect($mailContent)->toContain('John Smith')
            ->and($mailContent)->toContain('Acme Corp')
            ->and($mailContent)->toContain('Admin');
    });

    it('handles missing inviter name gracefully', function () {
        $workspace = createWorkspace(['name' => 'Test Workspace']);

        $invitation = WorkspaceInvitation::factory()
            ->forWorkspace($workspace)
            ->create([
                'email' => 'test@example.com',
                'role' => 'member',
                'invited_by' => null,
                'expires_at' => now()->addDays(7),
            ]);

        $notification = new WorkspaceInvitationNotification($invitation);
        $mail = $notification->toMail(new AnonymousNotifiable);

        $mailContent = implode(' ', array_map(fn ($line) => is_string($line) ? $line : '', $mail->introLines));
        expect($mailContent)->toContain('Someone');
    });

    it('includes action url', function () {
        [$workspace, $owner] = createWorkspaceWithOwner();

        $invitation = WorkspaceInvitation::factory()
            ->forWorkspace($workspace)
            ->invitedBy($owner)
            ->create(['expires_at' => now()->addDays(7)]);

        $notification = new WorkspaceInvitationNotification($invitation);
        $mail = $notification->toMail(new AnonymousNotifiable);

        expect($mail->actionUrl)->not->toBeEmpty()
            ->and($mail->actionText)->toBe('Accept Invitation');
    });

    it('converts to array format', function () {
        [$workspace, $owner] = createWorkspaceWithOwner(['name' => 'My Team']);

        $invitation = WorkspaceInvitation::factory()
            ->forWorkspace($workspace)
            ->invitedBy($owner)
            ->create([
                'role' => 'admin',
                'expires_at' => now()->addDays(7),
            ]);

        $notification = new WorkspaceInvitationNotification($invitation);
        $array = $notification->toArray(new AnonymousNotifiable);

        expect($array)->toHaveKeys(['invitation_id', 'workspace_id', 'workspace_name', 'role', 'expires_at'])
            ->and($array['workspace_id'])->toBe($workspace->id)
            ->and($array['workspace_name'])->toBe('My Team')
            ->and($array['role'])->toBe('admin');
    });

    it('is queueable', function () {
        $invitation = WorkspaceInvitation::factory()
            ->forWorkspace(createWorkspace())
            ->create(['expires_at' => now()->addDays(7)]);

        $notification = new WorkspaceInvitationNotification($invitation);

        expect($notification)->toBeInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class);
    });

    it('can be sent to anonymous notifiable', function () {
        Notification::fake();

        [$workspace, $owner] = createWorkspaceWithOwner();

        $invitation = WorkspaceInvitation::factory()
            ->forWorkspace($workspace)
            ->invitedBy($owner)
            ->create([
                'email' => 'recipient@example.com',
                'expires_at' => now()->addDays(7),
            ]);

        Notification::route('mail', 'recipient@example.com')
            ->notify(new WorkspaceInvitationNotification($invitation));

        Notification::assertSentTo(
            new AnonymousNotifiable,
            WorkspaceInvitationNotification::class,
            function ($notification, $channels, $notifiable) {
                return $notifiable->routes['mail'] === 'recipient@example.com';
            }
        );
    });
});
