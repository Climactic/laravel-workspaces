<?php

use Climactic\Workspaces\Events\WorkspaceSwitched;
use Illuminate\Support\Facades\Event;

describe('HasWorkspaces Trait', function () {
    describe('Workspace Relationships', function () {
        it('can get owned workspaces', function () {
            $user = createUser();
            $ownedWorkspace = createWorkspace(['name' => 'Owned'], $user);
            $otherWorkspace = createWorkspace(['name' => 'Other']);

            expect($user->ownedWorkspaces)->toHaveCount(1)
                ->and($user->ownedWorkspaces->first()->id)->toBe($ownedWorkspace->id);
        });

        it('can get all workspaces user belongs to', function () {
            $user = createUser();
            $workspace1 = createWorkspace(['name' => 'Workspace 1']);
            $workspace2 = createWorkspace(['name' => 'Workspace 2']);
            $workspace3 = createWorkspace(['name' => 'Workspace 3']);

            $workspace1->addMember($user, 'owner');
            $workspace2->addMember($user, 'member');
            // Not added to workspace3

            expect($user->workspaces)->toHaveCount(2)
                ->and($user->workspaces->pluck('name')->toArray())
                ->toContain('Workspace 1', 'Workspace 2')
                ->not->toContain('Workspace 3');
        });

        it('can get workspace memberships', function () {
            $user = createUser();
            $workspace = createWorkspace();
            $workspace->addMember($user, 'admin');

            expect($user->workspaceMemberships)->toHaveCount(1)
                ->and($user->workspaceMemberships->first()->role)->toBe('admin');
        });
    });

    describe('Current Workspace', function () {
        it('can get current workspace membership', function () {
            $user = createUser();
            $workspace = createWorkspace();
            $workspace->addMember($user, 'owner', setAsCurrent: true);

            expect($user->currentWorkspaceMembership)->not->toBeNull()
                ->and($user->currentWorkspaceMembership->workspace_id)->toBe($workspace->id)
                ->and($user->currentWorkspaceMembership->is_current)->toBeTrue();
        });

        it('can get current workspace', function () {
            $user = createUser();
            $workspace = createWorkspace(['name' => 'Current']);
            $workspace->addMember($user, 'owner', setAsCurrent: true);

            expect($user->currentWorkspace())->not->toBeNull()
                ->and($user->currentWorkspace()->name)->toBe('Current');
        });

        it('returns null when no current workspace', function () {
            $user = createUser();
            $workspace = createWorkspace();
            $workspace->addMember($user, 'member'); // Not set as current

            expect($user->currentWorkspace())->toBeNull();
        });

        it('has current_workspace_id accessor', function () {
            $user = createUser();
            $workspace = createWorkspace();
            $workspace->addMember($user, 'owner', setAsCurrent: true);

            expect($user->current_workspace_id)->toBe($workspace->id);
        });
    });

    describe('Workspace Switching', function () {
        it('can switch current workspace', function () {
            Event::fake([WorkspaceSwitched::class]);

            $user = createUser();
            $workspace1 = createWorkspace(['name' => 'First']);
            $workspace2 = createWorkspace(['name' => 'Second']);

            $workspace1->addMember($user, 'member', setAsCurrent: true);
            $workspace2->addMember($user, 'member');

            expect($user->currentWorkspace()->name)->toBe('First');

            $result = $user->switchWorkspace($workspace2);

            expect($result)->toBeTrue()
                ->and($user->fresh()->currentWorkspace()->name)->toBe('Second');

            Event::assertDispatched(WorkspaceSwitched::class);
        });

        it('returns false when switching to non-member workspace', function () {
            $user = createUser();
            $workspace1 = createWorkspace();
            $workspace2 = createWorkspace();

            $workspace1->addMember($user, 'member', setAsCurrent: true);
            // User is NOT a member of workspace2

            $result = $user->switchWorkspace($workspace2);

            expect($result)->toBeFalse()
                ->and($user->fresh()->currentWorkspace()->id)->toBe($workspace1->id);
        });

        it('can check if workspace is current', function () {
            $user = createUser();
            $workspace1 = createWorkspace();
            $workspace2 = createWorkspace();

            $workspace1->addMember($user, 'member', setAsCurrent: true);
            $workspace2->addMember($user, 'member');

            expect($user->isCurrentWorkspace($workspace1))->toBeTrue()
                ->and($user->isCurrentWorkspace($workspace2))->toBeFalse();
        });
    });

    describe('Membership Checks', function () {
        it('can check if user belongs to workspace', function () {
            $user = createUser();
            $workspace = createWorkspace();
            $otherWorkspace = createWorkspace();

            $workspace->addMember($user, 'member');

            expect($user->belongsToWorkspace($workspace))->toBeTrue()
                ->and($user->belongsToWorkspace($otherWorkspace))->toBeFalse();
        });

        it('can check if user owns workspace', function () {
            $user = createUser();
            $ownedWorkspace = createWorkspace(owner: $user);
            $otherWorkspace = createWorkspace();

            expect($user->ownsWorkspace($ownedWorkspace))->toBeTrue()
                ->and($user->ownsWorkspace($otherWorkspace))->toBeFalse();
        });

        it('belongsToWorkspace works with workspace ID', function () {
            $user = createUser();
            $workspace = createWorkspace();
            $workspace->addMember($user, 'member');

            expect($user->belongsToWorkspace($workspace->id))->toBeTrue();
        });
    });

    describe('Role Checks', function () {
        it('can get user role in workspace', function () {
            $user = createUser();
            $workspace = createWorkspace();
            $workspace->addMember($user, 'admin');

            expect($user->workspaceRole($workspace))->toBe('admin');
        });

        it('returns null role for non-member', function () {
            $user = createUser();
            $workspace = createWorkspace();

            expect($user->workspaceRole($workspace))->toBeNull();
        });

        it('can check if user has specific role', function () {
            $user = createUser();
            $workspace = createWorkspace();
            $workspace->addMember($user, 'admin');

            expect($user->hasWorkspaceRole($workspace, 'admin'))->toBeTrue()
                ->and($user->hasWorkspaceRole($workspace, 'owner'))->toBeFalse()
                ->and($user->hasWorkspaceRole($workspace, ['admin', 'owner']))->toBeTrue();
        });

        it('can check if user is workspace owner', function () {
            $user = createUser();
            $workspace = createWorkspace();
            $workspace->addMember($user, 'owner');

            expect($user->isWorkspaceOwner($workspace))->toBeTrue();
        });

        it('can check if user is workspace admin', function () {
            $owner = createUser();
            $admin = createUser();
            $member = createUser();
            $workspace = createWorkspace();

            $workspace->addMember($owner, 'owner');
            $workspace->addMember($admin, 'admin');
            $workspace->addMember($member, 'member');

            expect($owner->isWorkspaceAdmin($workspace))->toBeTrue()
                ->and($admin->isWorkspaceAdmin($workspace))->toBeTrue()
                ->and($member->isWorkspaceAdmin($workspace))->toBeFalse();
        });
    });

    describe('Permission Checks', function () {
        it('owner has all permissions', function () {
            $user = createUser();
            $workspace = createWorkspace();
            $workspace->addMember($user, 'owner');

            expect($user->hasWorkspacePermission($workspace, 'workspace.view'))->toBeTrue()
                ->and($user->hasWorkspacePermission($workspace, 'workspace.delete'))->toBeTrue()
                ->and($user->hasWorkspacePermission($workspace, 'members.invite'))->toBeTrue()
                ->and($user->hasWorkspacePermission($workspace, 'any.permission'))->toBeTrue();
        });

        it('member has limited permissions', function () {
            $user = createUser();
            $workspace = createWorkspace();
            $workspace->addMember($user, 'member');

            expect($user->hasWorkspacePermission($workspace, 'workspace.view'))->toBeTrue()
                ->and($user->hasWorkspacePermission($workspace, 'members.view'))->toBeTrue()
                ->and($user->hasWorkspacePermission($workspace, 'members.invite'))->toBeFalse()
                ->and($user->hasWorkspacePermission($workspace, 'workspace.delete'))->toBeFalse();
        });

        it('admin has wildcard permissions', function () {
            $user = createUser();
            $workspace = createWorkspace();
            $workspace->addMember($user, 'admin');

            // Admin has members.* which should match any members.X permission
            expect($user->hasWorkspacePermission($workspace, 'members.view'))->toBeTrue()
                ->and($user->hasWorkspacePermission($workspace, 'members.invite'))->toBeTrue()
                ->and($user->hasWorkspacePermission($workspace, 'members.remove'))->toBeTrue();
        });

        it('guest has minimal permissions', function () {
            $user = createUser();
            $workspace = createWorkspace();
            $workspace->addMember($user, 'guest');

            expect($user->hasWorkspacePermission($workspace, 'workspace.view'))->toBeTrue()
                ->and($user->hasWorkspacePermission($workspace, 'members.view'))->toBeFalse();
        });

        it('non-member has no permissions', function () {
            $user = createUser();
            $workspace = createWorkspace();
            // User is NOT a member

            expect($user->hasWorkspacePermission($workspace, 'workspace.view'))->toBeFalse();
        });

        it('can get all workspace permissions', function () {
            $user = createUser();
            $workspace = createWorkspace();
            $workspace->addMember($user, 'member');

            $permissions = $user->workspacePermissions($workspace);

            expect($permissions)->toBeArray()
                ->and($permissions)->toContain('workspace.view', 'members.view');
        });
    });

    describe('Personal Workspace', function () {
        it('can get personal workspace', function () {
            $user = createUser();
            $personalWorkspace = createWorkspace(['personal' => true], $user);
            $teamWorkspace = createWorkspace(['personal' => false], $user);

            expect($user->personalWorkspace())->not->toBeNull()
                ->and($user->personalWorkspace()->id)->toBe($personalWorkspace->id);
        });

        it('returns null when no personal workspace', function () {
            $user = createUser();
            createWorkspace(['personal' => false], $user);

            expect($user->personalWorkspace())->toBeNull();
        });
    });
});
