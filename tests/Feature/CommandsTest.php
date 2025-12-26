<?php

use Climactic\Workspaces\Models\WorkspaceInvitation;

describe('PruneInvitationsCommand', function () {
    it('deletes expired invitations with --expired-only flag', function () {
        $workspace = createWorkspace();

        // Create expired invitations
        WorkspaceInvitation::factory()
            ->forWorkspace($workspace)
            ->expired()
            ->count(3)
            ->create();

        // Create valid invitations
        WorkspaceInvitation::factory()
            ->forWorkspace($workspace)
            ->count(2)
            ->create(['expires_at' => now()->addDays(7)]);

        expect(WorkspaceInvitation::count())->toBe(5);

        $this->artisan('workspaces:prune-invitations --expired-only')
            ->expectsConfirmation('This will delete 3 invitation(s). Continue?', 'yes')
            ->expectsOutput('Successfully deleted 3 invitation(s).')
            ->assertExitCode(0);

        expect(WorkspaceInvitation::count())->toBe(2);
    });

    it('outputs no invitations message when none to prune', function () {
        $workspace = createWorkspace();

        WorkspaceInvitation::factory()
            ->forWorkspace($workspace)
            ->count(2)
            ->create(['expires_at' => now()->addDays(7)]);

        $this->artisan('workspaces:prune-invitations --expired-only')
            ->expectsOutput('No invitations to prune.')
            ->assertExitCode(0);
    });

    it('prunes by days option', function () {
        $workspace = createWorkspace();

        // Created 40 days ago
        WorkspaceInvitation::factory()
            ->forWorkspace($workspace)
            ->create(['created_at' => now()->subDays(40), 'expires_at' => now()->subDays(33)]);

        // Created 20 days ago
        WorkspaceInvitation::factory()
            ->forWorkspace($workspace)
            ->create(['created_at' => now()->subDays(20), 'expires_at' => now()->subDays(13)]);

        // Created today
        WorkspaceInvitation::factory()
            ->forWorkspace($workspace)
            ->create(['expires_at' => now()->addDays(7)]);

        expect(WorkspaceInvitation::count())->toBe(3);

        $this->artisan('workspaces:prune-invitations --days=30')
            ->expectsConfirmation('This will delete 1 invitation(s). Continue?', 'yes')
            ->expectsOutput('Successfully deleted 1 invitation(s).')
            ->assertExitCode(0);

        // Only the one created 40 days ago should be deleted
        expect(WorkspaceInvitation::count())->toBe(2);
    });

    it('can cancel the operation', function () {
        $workspace = createWorkspace();

        WorkspaceInvitation::factory()
            ->forWorkspace($workspace)
            ->expired()
            ->create();

        $this->artisan('workspaces:prune-invitations --expired-only')
            ->expectsConfirmation('This will delete 1 invitation(s). Continue?', 'no')
            ->expectsOutput('Operation cancelled.')
            ->assertExitCode(0);

        expect(WorkspaceInvitation::count())->toBe(1);
    });
});

describe('InstallCommand', function () {
    it('runs without errors in non-interactive mode', function () {
        // Mock the vendor:publish command to prevent actual file publishing in tests
        $this->artisan('workspaces:install --no-interaction')
            ->assertExitCode(0);
    })->skip('Install command publishes files which interferes with test database');
});
