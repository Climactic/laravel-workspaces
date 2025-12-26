<?php

use Climactic\Workspaces\Permissions\ConfigPermissionProvider;
use Climactic\Workspaces\Permissions\PermissionManager;

describe('ConfigPermissionProvider', function () {
    beforeEach(function () {
        $this->provider = new ConfigPermissionProvider;
    });

    describe('hasPermission', function () {
        it('returns true for owner with wildcard permission', function () {
            [$workspace, $owner] = createWorkspaceWithOwner();

            expect($this->provider->hasPermission($owner, $workspace, 'any.permission'))->toBeTrue()
                ->and($this->provider->hasPermission($owner, $workspace, 'workspace.delete'))->toBeTrue()
                ->and($this->provider->hasPermission($owner, $workspace, 'random.thing'))->toBeTrue();
        });

        it('returns true for exact permission match', function () {
            $user = createUser();
            $workspace = createWorkspace();
            $workspace->addMember($user, 'member');

            expect($this->provider->hasPermission($user, $workspace, 'workspace.view'))->toBeTrue()
                ->and($this->provider->hasPermission($user, $workspace, 'members.view'))->toBeTrue();
        });

        it('returns false for permission not in role', function () {
            $user = createUser();
            $workspace = createWorkspace();
            $workspace->addMember($user, 'member');

            expect($this->provider->hasPermission($user, $workspace, 'workspace.delete'))->toBeFalse()
                ->and($this->provider->hasPermission($user, $workspace, 'members.invite'))->toBeFalse();
        });

        it('supports wildcard patterns in role permissions', function () {
            $user = createUser();
            $workspace = createWorkspace();
            $workspace->addMember($user, 'admin');

            // Admin has 'members.*' which should match any members.X
            expect($this->provider->hasPermission($user, $workspace, 'members.view'))->toBeTrue()
                ->and($this->provider->hasPermission($user, $workspace, 'members.invite'))->toBeTrue()
                ->and($this->provider->hasPermission($user, $workspace, 'members.remove'))->toBeTrue()
                ->and($this->provider->hasPermission($user, $workspace, 'members.anything'))->toBeTrue();
        });

        it('returns false for non-member', function () {
            $user = createUser();
            $workspace = createWorkspace();
            // User is NOT a member

            expect($this->provider->hasPermission($user, $workspace, 'workspace.view'))->toBeFalse();
        });
    });

    describe('hasRole', function () {
        it('returns true when user has the role', function () {
            $user = createUser();
            $workspace = createWorkspace();
            $workspace->addMember($user, 'admin');

            expect($this->provider->hasRole($user, $workspace, 'admin'))->toBeTrue();
        });

        it('returns true when user has one of the roles', function () {
            $user = createUser();
            $workspace = createWorkspace();
            $workspace->addMember($user, 'admin');

            expect($this->provider->hasRole($user, $workspace, ['owner', 'admin']))->toBeTrue();
        });

        it('returns false when user does not have the role', function () {
            $user = createUser();
            $workspace = createWorkspace();
            $workspace->addMember($user, 'member');

            expect($this->provider->hasRole($user, $workspace, 'admin'))->toBeFalse();
        });
    });

    describe('getPermissions', function () {
        it('returns permissions for owner', function () {
            [$workspace, $owner] = createWorkspaceWithOwner();

            $permissions = $this->provider->getPermissions($owner, $workspace);

            expect($permissions)->toContain('*');
        });

        it('returns permissions for member', function () {
            $user = createUser();
            $workspace = createWorkspace();
            $workspace->addMember($user, 'member');

            $permissions = $this->provider->getPermissions($user, $workspace);

            expect($permissions)->toContain('workspace.view', 'members.view');
        });

        it('returns empty array for non-member', function () {
            $user = createUser();
            $workspace = createWorkspace();

            $permissions = $this->provider->getPermissions($user, $workspace);

            expect($permissions)->toBeEmpty();
        });
    });

    describe('getRole', function () {
        it('returns user role in workspace', function () {
            $user = createUser();
            $workspace = createWorkspace();
            $workspace->addMember($user, 'admin');

            expect($this->provider->getRole($user, $workspace))->toBe('admin');
        });

        it('returns null for non-member', function () {
            $user = createUser();
            $workspace = createWorkspace();

            expect($this->provider->getRole($user, $workspace))->toBeNull();
        });
    });

    describe('getAvailableRoles', function () {
        it('returns roles from config', function () {
            $roles = $this->provider->getAvailableRoles();

            expect($roles)->toContain('owner', 'admin', 'member', 'guest');
        });
    });

    describe('getAvailablePermissions', function () {
        it('returns permissions from config', function () {
            $permissions = $this->provider->getAvailablePermissions();

            expect($permissions)->toContain(
                'workspace.view',
                'workspace.update',
                'members.view',
                'members.invite'
            );
        });
    });
});

describe('PermissionManager', function () {
    it('uses config provider by default', function () {
        $manager = new PermissionManager;

        expect($manager->getProvider())->toBeInstanceOf(ConfigPermissionProvider::class);
    });

    it('delegates hasPermission to provider', function () {
        [$workspace, $owner] = createWorkspaceWithOwner();

        $manager = new PermissionManager;

        expect($manager->hasPermission($owner, $workspace, 'workspace.view'))->toBeTrue()
            ->and($manager->hasPermission($owner, $workspace, 'any.permission'))->toBeTrue();
    });

    it('delegates hasRole to provider', function () {
        [$workspace, $owner] = createWorkspaceWithOwner();

        $manager = new PermissionManager;

        expect($manager->hasRole($owner, $workspace, 'owner'))->toBeTrue()
            ->and($manager->hasRole($owner, $workspace, 'member'))->toBeFalse();
    });

    it('delegates getPermissions to provider', function () {
        [$workspace, $owner] = createWorkspaceWithOwner();

        $manager = new PermissionManager;
        $permissions = $manager->getPermissions($owner, $workspace);

        expect($permissions)->toContain('*');
    });

    it('delegates getRole to provider', function () {
        [$workspace, $owner] = createWorkspaceWithOwner();

        $manager = new PermissionManager;

        expect($manager->getRole($owner, $workspace))->toBe('owner');
    });

    it('delegates getAvailableRoles to provider', function () {
        $manager = new PermissionManager;

        expect($manager->getAvailableRoles())->toContain('owner', 'admin', 'member');
    });

    it('delegates getAvailablePermissions to provider', function () {
        $manager = new PermissionManager;

        expect($manager->getAvailablePermissions())->toContain('workspace.view');
    });
});
