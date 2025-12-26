<?php

use Climactic\Workspaces\Models\WorkspaceMembership;

describe('WorkspaceMembership Model', function () {
    it('can be created with factory', function () {
        $user = createUser();
        $workspace = createWorkspace();

        $membership = WorkspaceMembership::factory()
            ->forWorkspace($workspace)
            ->forUser($user)
            ->create();

        expect($membership)->toBeInstanceOf(WorkspaceMembership::class)
            ->and($membership->exists)->toBeTrue()
            ->and($membership->workspace_id)->toBe($workspace->id)
            ->and($membership->user_id)->toBe($user->id);
    });

    it('has workspace relationship', function () {
        $user = createUser();
        $workspace = createWorkspace(['name' => 'Test Workspace']);
        $workspace->addMember($user, 'member');

        $membership = WorkspaceMembership::where('user_id', $user->id)->first();

        expect($membership->workspace)->not->toBeNull()
            ->and($membership->workspace->name)->toBe('Test Workspace');
    });

    it('has user relationship', function () {
        $user = createUser(['name' => 'John Doe']);
        $workspace = createWorkspace();
        $workspace->addMember($user, 'member');

        $membership = WorkspaceMembership::where('workspace_id', $workspace->id)->first();

        expect($membership->user)->not->toBeNull()
            ->and($membership->user->name)->toBe('John Doe');
    });

    it('can check if is owner role', function () {
        $user = createUser();
        $workspace = createWorkspace();

        $workspace->addMember($user, 'owner');
        $ownerMembership = WorkspaceMembership::where('user_id', $user->id)->first();

        $member = createUser();
        $workspace->addMember($member, 'member');
        $memberMembership = WorkspaceMembership::where('user_id', $member->id)->first();

        expect($ownerMembership->isOwner())->toBeTrue()
            ->and($memberMembership->isOwner())->toBeFalse();
    });

    it('can check if is admin role', function () {
        $user = createUser();
        $workspace = createWorkspace();

        $workspace->addMember($user, 'admin');
        $adminMembership = WorkspaceMembership::where('user_id', $user->id)->first();

        $member = createUser();
        $workspace->addMember($member, 'member');
        $memberMembership = WorkspaceMembership::where('user_id', $member->id)->first();

        expect($adminMembership->isAdmin())->toBeTrue()
            ->and($memberMembership->isAdmin())->toBeFalse();
    });

    it('can check if is current workspace', function () {
        $user = createUser();
        $workspace1 = createWorkspace();
        $workspace2 = createWorkspace();

        $workspace1->addMember($user, 'member', setAsCurrent: true);
        $workspace2->addMember($user, 'member');

        $membership1 = WorkspaceMembership::where('user_id', $user->id)
            ->where('workspace_id', $workspace1->id)
            ->first();

        $membership2 = WorkspaceMembership::where('user_id', $user->id)
            ->where('workspace_id', $workspace2->id)
            ->first();

        expect($membership1->isCurrent())->toBeTrue()
            ->and($membership2->isCurrent())->toBeFalse();
    });

    it('can make itself current', function () {
        $user = createUser();
        $workspace1 = createWorkspace();
        $workspace2 = createWorkspace();

        $workspace1->addMember($user, 'member', setAsCurrent: true);
        $workspace2->addMember($user, 'member');

        $membership2 = WorkspaceMembership::where('user_id', $user->id)
            ->where('workspace_id', $workspace2->id)
            ->first();

        $membership2->makeCurrent();

        $membership1Fresh = WorkspaceMembership::where('user_id', $user->id)
            ->where('workspace_id', $workspace1->id)
            ->first();

        expect($membership1Fresh->is_current)->toBeFalse()
            ->and($membership2->fresh()->is_current)->toBeTrue();
    });

    it('has joined_at timestamp', function () {
        $user = createUser();
        $workspace = createWorkspace();
        $workspace->addMember($user, 'member');

        $membership = WorkspaceMembership::where('user_id', $user->id)->first();

        expect($membership->joined_at)->not->toBeNull()
            ->and($membership->joined_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
    });

    it('can store custom permissions', function () {
        $user = createUser();
        $workspace = createWorkspace();

        $membership = WorkspaceMembership::factory()
            ->forWorkspace($workspace)
            ->forUser($user)
            ->withPermissions(['custom.read', 'custom.write'])
            ->create();

        expect($membership->permissions)->toBeArray()
            ->and($membership->permissions)->toContain('custom.read', 'custom.write');
    });

    it('uses configured table name', function () {
        $membership = new WorkspaceMembership;

        expect($membership->getTable())->toBe(config('workspaces.tables.memberships', 'workspace_memberships'));
    });

    describe('Factory States', function () {
        it('can create owner membership', function () {
            $user = createUser();
            $workspace = createWorkspace();

            $membership = WorkspaceMembership::factory()
                ->forWorkspace($workspace)
                ->forUser($user)
                ->owner()
                ->create();

            expect($membership->role)->toBe('owner');
        });

        it('can create admin membership', function () {
            $user = createUser();
            $workspace = createWorkspace();

            $membership = WorkspaceMembership::factory()
                ->forWorkspace($workspace)
                ->forUser($user)
                ->admin()
                ->create();

            expect($membership->role)->toBe('admin');
        });

        it('can create current membership', function () {
            $user = createUser();
            $workspace = createWorkspace();

            $membership = WorkspaceMembership::factory()
                ->forWorkspace($workspace)
                ->forUser($user)
                ->current()
                ->create();

            expect($membership->is_current)->toBeTrue();
        });
    });
});
