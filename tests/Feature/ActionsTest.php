<?php

use Climactic\Workspaces\Actions\AcceptInvitation;
use Climactic\Workspaces\Actions\AddWorkspaceMember;
use Climactic\Workspaces\Actions\CancelInvitation;
use Climactic\Workspaces\Actions\CreateInvitation;
use Climactic\Workspaces\Actions\CreateWorkspace;
use Climactic\Workspaces\Actions\DeclineInvitation;
use Climactic\Workspaces\Actions\DeleteWorkspace;
use Climactic\Workspaces\Actions\RemoveWorkspaceMember;
use Climactic\Workspaces\Actions\UpdateMemberRole;
use Climactic\Workspaces\Events\InvitationAccepted;
use Climactic\Workspaces\Events\InvitationCancelled;
use Climactic\Workspaces\Events\InvitationCreated;
use Climactic\Workspaces\Events\InvitationDeclined;
use Climactic\Workspaces\Events\MemberAdded;
use Climactic\Workspaces\Events\MemberRemoved;
use Climactic\Workspaces\Events\MemberRoleUpdated;
use Climactic\Workspaces\Events\WorkspaceCreated;
use Climactic\Workspaces\Events\WorkspaceDeleted;
use Climactic\Workspaces\Exceptions\InvitationAlreadyAcceptedException;
use Climactic\Workspaces\Exceptions\InvitationEmailMismatchException;
use Climactic\Workspaces\Exceptions\InvitationExpiredException;
use Climactic\Workspaces\Models\Workspace;
use Climactic\Workspaces\Models\WorkspaceInvitation;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

describe('CreateWorkspace Action', function () {
    it('creates a workspace', function () {
        Event::fake([WorkspaceCreated::class]);

        $action = new CreateWorkspace;
        $workspace = $action->execute(['name' => 'My Workspace']);

        expect($workspace)->toBeInstanceOf(Workspace::class)
            ->and($workspace->name)->toBe('My Workspace')
            ->and($workspace->exists)->toBeTrue();

        Event::assertDispatched(WorkspaceCreated::class);
    });

    it('creates workspace with owner', function () {
        $owner = createUser();
        $action = new CreateWorkspace;

        $workspace = $action->execute(['name' => 'Team Workspace'], $owner);

        expect($workspace->owner_id)->toBe($owner->id)
            ->and($workspace->hasUser($owner))->toBeTrue()
            ->and($workspace->getMemberRole($owner))->toBe('owner');
    });

    it('creates workspace and sets as current', function () {
        $owner = createUser();
        $action = new CreateWorkspace;

        $workspace = $action->execute(['name' => 'Current Workspace'], $owner, setAsCurrent: true);

        expect($owner->fresh()->currentWorkspace()->id)->toBe($workspace->id);
    });

    it('creates personal workspace', function () {
        $owner = createUser();
        $action = new CreateWorkspace;

        $workspace = $action->execute([
            'name' => 'Personal',
            'personal' => true,
        ], $owner);

        expect($workspace->isPersonal())->toBeTrue();
    });
});

describe('DeleteWorkspace Action', function () {
    it('deletes a workspace', function () {
        Event::fake([WorkspaceDeleted::class]);

        [$workspace, $owner] = createWorkspaceWithOwner();
        $workspaceId = $workspace->id;

        $action = new DeleteWorkspace;
        $action->execute($workspace);

        // With soft deletes, it should be soft deleted
        expect(Workspace::find($workspaceId))->toBeNull();

        Event::assertDispatched(WorkspaceDeleted::class);
    });

    it('can force delete workspace', function () {
        [$workspace, $owner] = createWorkspaceWithOwner();
        $workspaceId = $workspace->id;

        $action = new DeleteWorkspace;
        $action->execute($workspace, force: true);

        expect(Workspace::withTrashed()->find($workspaceId))->toBeNull();
    });
});

describe('AddWorkspaceMember Action', function () {
    it('adds a member to workspace', function () {
        Event::fake([MemberAdded::class]);

        $workspace = createWorkspace();
        $user = createUser();

        $action = new AddWorkspaceMember;
        $action->execute($workspace, $user, 'member');

        expect($workspace->hasUser($user))->toBeTrue()
            ->and($workspace->getMemberRole($user))->toBe('member');

        Event::assertDispatched(MemberAdded::class);
    });

    it('uses default role when not specified', function () {
        $workspace = createWorkspace();
        $user = createUser();

        $action = new AddWorkspaceMember;
        $action->execute($workspace, $user);

        expect($workspace->getMemberRole($user))->toBe(config('workspaces.default_role'));
    });

    it('can set member as current', function () {
        $workspace = createWorkspace();
        $user = createUser();

        $action = new AddWorkspaceMember;
        $action->execute($workspace, $user, 'member', setAsCurrent: true);

        expect($user->fresh()->currentWorkspace()->id)->toBe($workspace->id);
    });
});

describe('RemoveWorkspaceMember Action', function () {
    it('removes a member from workspace', function () {
        Event::fake([MemberRemoved::class]);

        [$workspace, $owner] = createWorkspaceWithOwner();
        $member = createUser();
        $workspace->addMember($member, 'member');

        expect($workspace->hasUser($member))->toBeTrue();

        $action = new RemoveWorkspaceMember;
        $action->execute($workspace, $member);

        expect($workspace->fresh()->hasUser($member))->toBeFalse();

        Event::assertDispatched(MemberRemoved::class);
    });
});

describe('UpdateMemberRole Action', function () {
    it('updates member role', function () {
        Event::fake([MemberRoleUpdated::class]);

        [$workspace, $owner] = createWorkspaceWithOwner();
        $member = createUser();
        $workspace->addMember($member, 'member');

        $action = new UpdateMemberRole;
        $action->execute($workspace, $member, 'admin');

        expect($workspace->getMemberRole($member))->toBe('admin');

        Event::assertDispatched(MemberRoleUpdated::class, function ($event) {
            return $event->oldRole === 'member' && $event->newRole === 'admin';
        });
    });
});

describe('CreateInvitation Action', function () {
    it('creates an invitation', function () {
        Event::fake([InvitationCreated::class]);
        Notification::fake();

        [$workspace, $owner] = createWorkspaceWithOwner();

        $action = new CreateInvitation;
        $invitation = $action->execute($workspace, 'invite@example.com', 'member', $owner);

        expect($invitation)->toBeInstanceOf(WorkspaceInvitation::class)
            ->and($invitation->email)->toBe('invite@example.com')
            ->and($invitation->role)->toBe('member')
            ->and($invitation->workspace_id)->toBe($workspace->id)
            ->and($invitation->invited_by)->toBe($owner->id);

        Event::assertDispatched(InvitationCreated::class);
    });

    it('generates unique token', function () {
        Notification::fake();

        [$workspace, $owner] = createWorkspaceWithOwner();

        $action = new CreateInvitation;
        $invitation1 = $action->execute($workspace, 'user1@example.com', 'member', $owner);
        $invitation2 = $action->execute($workspace, 'user2@example.com', 'member', $owner);

        expect($invitation1->token)->not->toBe($invitation2->token);
    });
});

describe('AcceptInvitation Action', function () {
    beforeEach(function () {
        Notification::fake();
    });

    it('accepts an invitation and adds user to workspace', function () {
        Event::fake([InvitationAccepted::class]);

        [$workspace, $owner] = createWorkspaceWithOwner();
        $newUser = createUser(['email' => 'invitee@example.com']);
        $invitation = WorkspaceInvitation::factory()
            ->forWorkspace($workspace)
            ->invitedBy($owner)
            ->create(['email' => 'invitee@example.com', 'role' => 'member', 'expires_at' => now()->addDays(7)]);

        $action = new AcceptInvitation;
        $resultWorkspace = $action->execute($invitation, $newUser);

        expect($resultWorkspace->id)->toBe($workspace->id)
            ->and($workspace->hasUser($newUser))->toBeTrue()
            ->and($workspace->getMemberRole($newUser))->toBe('member')
            ->and($invitation->fresh()->isAccepted())->toBeTrue();

        Event::assertDispatched(InvitationAccepted::class);
    });

    it('can accept by token', function () {
        [$workspace, $owner] = createWorkspaceWithOwner();
        $newUser = createUser(['email' => 'token-accept@example.com']);
        $invitation = WorkspaceInvitation::factory()
            ->forWorkspace($workspace)
            ->create(['email' => 'token-accept@example.com', 'expires_at' => now()->addDays(7)]);

        $action = new AcceptInvitation;
        $resultWorkspace = $action->execute($invitation->token, $newUser);

        expect($resultWorkspace->id)->toBe($workspace->id);
    });

    it('throws exception for already accepted invitation', function () {
        [$workspace, $owner] = createWorkspaceWithOwner();
        $invitation = WorkspaceInvitation::factory()
            ->forWorkspace($workspace)
            ->accepted()
            ->create();

        $newUser = createUser();

        $action = new AcceptInvitation;

        expect(fn () => $action->execute($invitation, $newUser))
            ->toThrow(InvitationAlreadyAcceptedException::class);
    });

    it('throws exception for expired invitation', function () {
        [$workspace, $owner] = createWorkspaceWithOwner();
        $invitation = WorkspaceInvitation::factory()
            ->forWorkspace($workspace)
            ->expired()
            ->create();

        $newUser = createUser();

        $action = new AcceptInvitation;

        expect(fn () => $action->execute($invitation, $newUser))
            ->toThrow(InvitationExpiredException::class);
    });

    it('throws exception when user email does not match invitation email', function () {
        [$workspace, $owner] = createWorkspaceWithOwner();
        $invitation = WorkspaceInvitation::factory()
            ->forWorkspace($workspace)
            ->create(['email' => 'invited@example.com', 'expires_at' => now()->addDays(7)]);

        $differentUser = createUser(['email' => 'different@example.com']);

        $action = new AcceptInvitation;

        expect(fn () => $action->execute($invitation, $differentUser))
            ->toThrow(InvitationEmailMismatchException::class);
    });

    it('sets workspace as current if user has no current workspace', function () {
        [$workspace, $owner] = createWorkspaceWithOwner();
        $newUser = createUser(['email' => 'current-workspace@example.com']);
        $invitation = WorkspaceInvitation::factory()
            ->forWorkspace($workspace)
            ->create(['email' => 'current-workspace@example.com', 'expires_at' => now()->addDays(7)]);

        $action = new AcceptInvitation;
        $action->execute($invitation, $newUser);

        expect($newUser->fresh()->currentWorkspace()->id)->toBe($workspace->id);
    });
});

describe('DeclineInvitation Action', function () {
    it('declines an invitation', function () {
        Event::fake([InvitationDeclined::class]);

        [$workspace, $owner] = createWorkspaceWithOwner();
        $invitation = WorkspaceInvitation::factory()
            ->forWorkspace($workspace)
            ->create(['expires_at' => now()->addDays(7)]);

        $action = new DeclineInvitation;
        $action->execute($invitation);

        expect($invitation->fresh()->isDeclined())->toBeTrue();

        Event::assertDispatched(InvitationDeclined::class);
    });
});

describe('CancelInvitation Action', function () {
    it('cancels an invitation by deleting it', function () {
        Event::fake([InvitationCancelled::class]);

        [$workspace, $owner] = createWorkspaceWithOwner();
        $invitation = WorkspaceInvitation::factory()
            ->forWorkspace($workspace)
            ->create(['expires_at' => now()->addDays(7)]);

        $invitationId = $invitation->id;

        $action = new CancelInvitation;
        $action->execute($invitation);

        expect(WorkspaceInvitation::find($invitationId))->toBeNull();

        Event::assertDispatched(InvitationCancelled::class);
    });
});
