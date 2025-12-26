<?php

use Climactic\Workspaces\Events\MemberAdded;
use Climactic\Workspaces\Events\MemberRemoved;
use Climactic\Workspaces\Events\MemberRoleUpdated;
use Climactic\Workspaces\Models\Workspace;
use Climactic\Workspaces\Models\WorkspaceMembership;
use Illuminate\Support\Facades\Event;

describe('Workspace Model', function () {
    it('can be created with factory', function () {
        $workspace = createWorkspace(['name' => 'Test Workspace']);

        expect($workspace)->toBeInstanceOf(Workspace::class)
            ->and($workspace->name)->toBe('Test Workspace')
            ->and($workspace->exists)->toBeTrue();
    });

    it('has an owner relationship', function () {
        $user = createUser();
        $workspace = createWorkspace(owner: $user);

        expect($workspace->owner)->not->toBeNull()
            ->and($workspace->owner->id)->toBe($user->id);
    });

    it('can have members', function () {
        [$workspace, $owner] = createWorkspaceWithOwner();
        $member = createUser();

        $workspace->addMember($member, 'member');

        expect($workspace->members)->toHaveCount(2)
            ->and($workspace->members->pluck('id'))->toContain($owner->id, $member->id);
    });

    it('can check if user is a member', function () {
        [$workspace, $owner] = createWorkspaceWithOwner();
        $nonMember = createUser();

        expect($workspace->hasUser($owner))->toBeTrue()
            ->and($workspace->hasUser($nonMember))->toBeFalse();
    });

    it('can check if user has specific role', function () {
        [$workspace, $owner] = createWorkspaceWithOwner();
        $member = createUser();
        $workspace->addMember($member, 'member');

        expect($workspace->hasUserWithRole($owner, 'owner'))->toBeTrue()
            ->and($workspace->hasUserWithRole($owner, 'member'))->toBeFalse()
            ->and($workspace->hasUserWithRole($member, 'member'))->toBeTrue();
    });

    it('can get member role', function () {
        [$workspace, $owner] = createWorkspaceWithOwner();
        $member = createUser();
        $workspace->addMember($member, 'admin');

        expect($workspace->getMemberRole($owner))->toBe('owner')
            ->and($workspace->getMemberRole($member))->toBe('admin');
    });

    it('returns null role for non-member', function () {
        $workspace = createWorkspace();
        $nonMember = createUser();

        expect($workspace->getMemberRole($nonMember))->toBeNull();
    });

    it('fires event when adding member', function () {
        Event::fake([MemberAdded::class]);

        [$workspace, $owner] = createWorkspaceWithOwner();
        $member = createUser();

        $workspace->addMember($member, 'member');

        Event::assertDispatched(MemberAdded::class, function ($event) use ($workspace, $member) {
            return $event->workspace->id === $workspace->id
                && $event->user->id === $member->id
                && $event->role === 'member';
        });
    });

    it('can remove a member', function () {
        Event::fake([MemberRemoved::class]);

        [$workspace, $owner] = createWorkspaceWithOwner();
        $member = createUser();
        $workspace->addMember($member, 'member');

        expect($workspace->hasUser($member))->toBeTrue();

        $workspace->removeMember($member);

        expect($workspace->fresh()->hasUser($member))->toBeFalse();

        Event::assertDispatched(MemberRemoved::class, function ($event) use ($workspace, $member) {
            return $event->workspace->id === $workspace->id
                && $event->user->id === $member->id;
        });
    });

    it('can update member role', function () {
        Event::fake([MemberRoleUpdated::class]);

        [$workspace, $owner] = createWorkspaceWithOwner();
        $member = createUser();
        $workspace->addMember($member, 'member');

        $workspace->updateMemberRole($member, 'admin');

        expect($workspace->getMemberRole($member))->toBe('admin');

        Event::assertDispatched(MemberRoleUpdated::class, function ($event) use ($member) {
            return $event->user->id === $member->id
                && $event->oldRole === 'member'
                && $event->newRole === 'admin';
        });
    });

    it('can be set as current in container', function () {
        $workspace = createWorkspace(['name' => 'Current Workspace']);

        $workspace->makeCurrent();

        expect(Workspace::current())->not->toBeNull()
            ->and(Workspace::current()->id)->toBe($workspace->id)
            ->and(Workspace::checkCurrent())->toBeTrue();
    });

    it('can forget current workspace', function () {
        $workspace = createWorkspace();
        $workspace->makeCurrent();

        expect(Workspace::checkCurrent())->toBeTrue();

        $workspace->forgetCurrent();

        expect(Workspace::checkCurrent())->toBeFalse();
    });

    it('can identify personal workspace', function () {
        $personalWorkspace = createWorkspace(['personal' => true]);
        $teamWorkspace = createWorkspace(['personal' => false]);

        expect($personalWorkspace->isPersonal())->toBeTrue()
            ->and($teamWorkspace->isPersonal())->toBeFalse();
    });

    it('has memberships relationship', function () {
        [$workspace, $owner] = createWorkspaceWithOwner();
        $member = createUser();
        $workspace->addMember($member, 'member');

        expect($workspace->memberships)->toHaveCount(2)
            ->and($workspace->memberships->first())->toBeInstanceOf(WorkspaceMembership::class);
    });

    it('uses configured table name', function () {
        $workspace = new Workspace;

        expect($workspace->getTable())->toBe(config('workspaces.tables.workspaces', 'workspaces'));
    });

    it('supports soft deletes when configured', function () {
        config()->set('workspaces.soft_deletes', true);

        $workspace = createWorkspace();
        $workspaceId = $workspace->id;

        $workspace->delete();

        expect(Workspace::find($workspaceId))->toBeNull()
            ->and(Workspace::withTrashed()->find($workspaceId))->not->toBeNull();
    });
});

describe('Workspace Members with Set As Current', function () {
    it('can add member and set as current', function () {
        $workspace = createWorkspace();
        $user = createUser();

        $workspace->addMember($user, 'owner', setAsCurrent: true);

        $membership = WorkspaceMembership::where('user_id', $user->id)
            ->where('workspace_id', $workspace->id)
            ->first();

        expect($membership->is_current)->toBeTrue();
    });

    it('clears previous current when setting new current', function () {
        $workspace1 = createWorkspace();
        $workspace2 = createWorkspace();
        $user = createUser();

        $workspace1->addMember($user, 'member', setAsCurrent: true);
        $workspace2->addMember($user, 'member', setAsCurrent: true);

        $membership1 = WorkspaceMembership::where('user_id', $user->id)
            ->where('workspace_id', $workspace1->id)
            ->first();

        $membership2 = WorkspaceMembership::where('user_id', $user->id)
            ->where('workspace_id', $workspace2->id)
            ->first();

        expect($membership1->is_current)->toBeFalse()
            ->and($membership2->is_current)->toBeTrue();
    });
});
