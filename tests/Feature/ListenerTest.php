<?php

use Climactic\Workspaces\Listeners\CreateWorkspaceOnRegistration;
use Climactic\Workspaces\Models\Workspace;
use Illuminate\Auth\Events\Registered;

describe('CreateWorkspaceOnRegistration Listener', function () {
    it('creates a personal workspace on user registration', function () {
        config()->set('workspaces.auto_create_on_registration.enabled', true);

        $user = createUser(['name' => 'John Doe']);

        // Simulate registration event
        $listener = new CreateWorkspaceOnRegistration;
        $listener->handle(new Registered($user));

        expect($user->workspaces()->count())->toBe(1);

        $workspace = $user->workspaces()->first();
        expect($workspace->name)->toBe("John Doe's Workspace")
            ->and($workspace->isPersonal())->toBeTrue()
            ->and($workspace->owner_id)->toBe($user->id);
    });

    it('sets workspace as current for the user', function () {
        config()->set('workspaces.auto_create_on_registration.enabled', true);

        $user = createUser(['name' => 'Jane']);

        $listener = new CreateWorkspaceOnRegistration;
        $listener->handle(new Registered($user));

        expect($user->fresh()->currentWorkspace())->not->toBeNull()
            ->and($user->fresh()->currentWorkspace()->name)->toBe("Jane's Workspace");
    });

    it('skips when auto-creation is disabled', function () {
        config()->set('workspaces.auto_create_on_registration.enabled', false);

        $user = createUser();

        $listener = new CreateWorkspaceOnRegistration;
        $listener->handle(new Registered($user));

        expect($user->workspaces()->count())->toBe(0);
    });

    it('uses configured name field', function () {
        config()->set('workspaces.auto_create_on_registration.enabled', true);
        config()->set('workspaces.auto_create_on_registration.name_from', 'email');

        $user = createUser(['name' => 'John', 'email' => 'john@example.com']);

        $listener = new CreateWorkspaceOnRegistration;
        $listener->handle(new Registered($user));

        $workspace = $user->workspaces()->first();
        expect($workspace->name)->toBe("john@example.com's Workspace");
    });

    it('uses configured name suffix', function () {
        config()->set('workspaces.auto_create_on_registration.enabled', true);
        config()->set('workspaces.auto_create_on_registration.name_suffix', ' Team');

        $user = createUser(['name' => 'Alice']);

        $listener = new CreateWorkspaceOnRegistration;
        $listener->handle(new Registered($user));

        $workspace = $user->workspaces()->first();
        expect($workspace->name)->toBe('Alice Team');
    });

    it('skips if user already has workspaces', function () {
        config()->set('workspaces.auto_create_on_registration.enabled', true);

        // Create user and workspace with membership using the helper
        [$existingWorkspace, $user] = createWorkspaceWithOwner(
            ['name' => 'Existing'],
            ['name' => 'Bob']
        );

        // Verify the user has 1 workspace before listener
        expect($user->fresh()->workspaces()->count())->toBe(1);

        $listener = new CreateWorkspaceOnRegistration;
        $listener->handle(new Registered($user->fresh()));

        // Should still have only 1 workspace - listener should have skipped
        expect($user->fresh()->workspaces()->count())->toBe(1)
            ->and($user->fresh()->workspaces->pluck('name')->toArray())->toContain('Existing');
    });

    it('uses default name when name attribute is missing', function () {
        config()->set('workspaces.auto_create_on_registration.enabled', true);
        config()->set('workspaces.auto_create_on_registration.name_from', 'nonexistent_field');

        $user = createUser(['name' => 'Test User']);

        $listener = new CreateWorkspaceOnRegistration;
        $listener->handle(new Registered($user));

        $workspace = $user->workspaces()->first();
        expect($workspace->name)->toBe("User's Workspace");
    });
});
