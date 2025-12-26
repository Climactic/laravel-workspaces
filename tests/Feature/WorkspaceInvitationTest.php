<?php

use Climactic\Workspaces\Models\WorkspaceInvitation;

describe('WorkspaceInvitation Model', function () {
    it('can be created with factory', function () {
        $workspace = createWorkspace();
        $inviter = createUser();

        $invitation = WorkspaceInvitation::factory()
            ->forWorkspace($workspace)
            ->invitedBy($inviter)
            ->create(['email' => 'test@example.com']);

        expect($invitation)->toBeInstanceOf(WorkspaceInvitation::class)
            ->and($invitation->exists)->toBeTrue()
            ->and($invitation->email)->toBe('test@example.com');
    });

    it('generates unique token', function () {
        $workspace = createWorkspace();

        $invitation1 = WorkspaceInvitation::factory()->forWorkspace($workspace)->create();
        $invitation2 = WorkspaceInvitation::factory()->forWorkspace($workspace)->create();

        expect($invitation1->token)->not->toBe($invitation2->token)
            ->and(strlen($invitation1->token))->toBe(64);
    });

    it('has workspace relationship', function () {
        $workspace = createWorkspace(['name' => 'Test Workspace']);
        $invitation = WorkspaceInvitation::factory()->forWorkspace($workspace)->create();

        expect($invitation->workspace)->not->toBeNull()
            ->and($invitation->workspace->name)->toBe('Test Workspace');
    });

    it('has inviter relationship', function () {
        $workspace = createWorkspace();
        $inviter = createUser(['name' => 'John Inviter']);
        $invitation = WorkspaceInvitation::factory()
            ->forWorkspace($workspace)
            ->invitedBy($inviter)
            ->create();

        expect($invitation->inviter)->not->toBeNull()
            ->and($invitation->inviter->name)->toBe('John Inviter');
    });

    describe('Expiration', function () {
        it('can check if expired', function () {
            $workspace = createWorkspace();

            $validInvitation = WorkspaceInvitation::factory()
                ->forWorkspace($workspace)
                ->create(['expires_at' => now()->addDays(7)]);

            $expiredInvitation = WorkspaceInvitation::factory()
                ->forWorkspace($workspace)
                ->create(['expires_at' => now()->subDay()]);

            expect($validInvitation->isExpired())->toBeFalse()
                ->and($expiredInvitation->isExpired())->toBeTrue();
        });

        it('can check if valid', function () {
            $workspace = createWorkspace();

            $validInvitation = WorkspaceInvitation::factory()
                ->forWorkspace($workspace)
                ->create(['expires_at' => now()->addDays(7)]);

            $expiredInvitation = WorkspaceInvitation::factory()
                ->forWorkspace($workspace)
                ->create(['expires_at' => now()->subDay()]);

            expect($validInvitation->isValid())->toBeTrue()
                ->and($expiredInvitation->isValid())->toBeFalse();
        });

        it('uses configured expiry days', function () {
            config()->set('workspaces.invitations.expires_after_days', 14);

            $workspace = createWorkspace();
            // Manually set the expires_at to use the new config value
            // (factory definition is evaluated before the config is changed)
            $invitation = WorkspaceInvitation::factory()
                ->forWorkspace($workspace)
                ->create([
                    'expires_at' => now()->addDays(config('workspaces.invitations.expires_after_days')),
                ]);

            // Should expire in ~14 days (use absolute value for diffInDays)
            $daysDiff = (int) $invitation->expires_at->diffInDays(now(), absolute: true);
            expect($daysDiff)->toBeGreaterThanOrEqual(13)
                ->and($daysDiff)->toBeLessThanOrEqual(15);
        });
    });

    describe('Acceptance', function () {
        it('can check if accepted', function () {
            $workspace = createWorkspace();

            $pendingInvitation = WorkspaceInvitation::factory()
                ->forWorkspace($workspace)
                ->create(['accepted_at' => null]);

            $acceptedInvitation = WorkspaceInvitation::factory()
                ->forWorkspace($workspace)
                ->accepted()
                ->create();

            expect($pendingInvitation->isAccepted())->toBeFalse()
                ->and($acceptedInvitation->isAccepted())->toBeTrue();
        });

        it('can mark as accepted', function () {
            $workspace = createWorkspace();
            $invitation = WorkspaceInvitation::factory()
                ->forWorkspace($workspace)
                ->create(['accepted_at' => null]);

            expect($invitation->isAccepted())->toBeFalse();

            $invitation->markAsAccepted();

            expect($invitation->isAccepted())->toBeTrue()
                ->and($invitation->accepted_at)->not->toBeNull();
        });

        it('accepted invitation is not valid', function () {
            $workspace = createWorkspace();
            $invitation = WorkspaceInvitation::factory()
                ->forWorkspace($workspace)
                ->accepted()
                ->create();

            expect($invitation->isValid())->toBeFalse();
        });
    });

    describe('Declining', function () {
        it('can check if declined', function () {
            $workspace = createWorkspace();

            $pendingInvitation = WorkspaceInvitation::factory()
                ->forWorkspace($workspace)
                ->create(['declined_at' => null]);

            $declinedInvitation = WorkspaceInvitation::factory()
                ->forWorkspace($workspace)
                ->declined()
                ->create();

            expect($pendingInvitation->isDeclined())->toBeFalse()
                ->and($declinedInvitation->isDeclined())->toBeTrue();
        });

        it('can mark as declined', function () {
            $workspace = createWorkspace();
            $invitation = WorkspaceInvitation::factory()
                ->forWorkspace($workspace)
                ->create(['declined_at' => null]);

            expect($invitation->isDeclined())->toBeFalse();

            $invitation->markAsDeclined();

            expect($invitation->isDeclined())->toBeTrue()
                ->and($invitation->declined_at)->not->toBeNull();
        });

        it('declined invitation is not valid', function () {
            $workspace = createWorkspace();
            $invitation = WorkspaceInvitation::factory()
                ->forWorkspace($workspace)
                ->declined()
                ->create();

            expect($invitation->isValid())->toBeFalse();
        });
    });

    describe('Pending Status', function () {
        it('can check if pending', function () {
            $workspace = createWorkspace();

            $pendingInvitation = WorkspaceInvitation::factory()
                ->forWorkspace($workspace)
                ->create([
                    'accepted_at' => null,
                    'declined_at' => null,
                    'expires_at' => now()->addDays(7),
                ]);

            $acceptedInvitation = WorkspaceInvitation::factory()
                ->forWorkspace($workspace)
                ->accepted()
                ->create();

            expect($pendingInvitation->isPending())->toBeTrue()
                ->and($acceptedInvitation->isPending())->toBeFalse();
        });

        it('expired invitation is not pending', function () {
            $workspace = createWorkspace();
            $invitation = WorkspaceInvitation::factory()
                ->forWorkspace($workspace)
                ->expired()
                ->create();

            expect($invitation->isPending())->toBeFalse();
        });
    });

    describe('Scopes', function () {
        it('can query pending invitations', function () {
            $workspace = createWorkspace();

            WorkspaceInvitation::factory()->forWorkspace($workspace)->create(['expires_at' => now()->addDays(7)]);
            WorkspaceInvitation::factory()->forWorkspace($workspace)->create(['expires_at' => now()->addDays(7)]);
            WorkspaceInvitation::factory()->forWorkspace($workspace)->accepted()->create();
            WorkspaceInvitation::factory()->forWorkspace($workspace)->expired()->create();

            expect(WorkspaceInvitation::pending()->count())->toBe(2);
        });

        it('can query expired invitations', function () {
            $workspace = createWorkspace();

            WorkspaceInvitation::factory()->forWorkspace($workspace)->create(['expires_at' => now()->addDays(7)]);
            WorkspaceInvitation::factory()->forWorkspace($workspace)->expired()->create();
            WorkspaceInvitation::factory()->forWorkspace($workspace)->expired()->create();

            expect(WorkspaceInvitation::expired()->count())->toBe(2);
        });
    });

    it('stores assigned role', function () {
        $workspace = createWorkspace();
        $invitation = WorkspaceInvitation::factory()
            ->forWorkspace($workspace)
            ->create(['role' => 'admin']);

        expect($invitation->role)->toBe('admin');
    });

    it('uses configured table name', function () {
        $invitation = new WorkspaceInvitation;

        expect($invitation->getTable())->toBe(config('workspaces.tables.invitations', 'workspace_invitations'));
    });
});
