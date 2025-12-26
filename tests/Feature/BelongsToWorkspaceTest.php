<?php

use Climactic\Workspaces\Exceptions\NoCurrentWorkspaceException;
use Climactic\Workspaces\Models\Workspace;
use Climactic\Workspaces\Scopes\WorkspaceScope;
use Climactic\Workspaces\Tests\Fixtures\Project;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    // Create the projects table for testing
    if (! Schema::hasTable('projects')) {
        Schema::create('projects', function ($table) {
            $table->id();
            $table->string('name');
            $table->foreignId('workspace_id')->nullable();
            $table->timestamps();
        });
    }
});

describe('BelongsToWorkspace Trait', function () {
    describe('Global Scope', function () {
        it('applies workspace scope automatically', function () {
            $workspace1 = createWorkspace(['name' => 'Workspace 1']);
            $workspace2 = createWorkspace(['name' => 'Workspace 2']);

            // Create projects in each workspace
            $workspace1->makeCurrent();
            $project1 = Project::create(['name' => 'Project 1']);

            $workspace2->makeCurrent();
            $project2 = Project::create(['name' => 'Project 2']);

            // Query with workspace 1 as current
            $workspace1->makeCurrent();
            $projects = Project::all();

            expect($projects)->toHaveCount(1)
                ->and($projects->first()->name)->toBe('Project 1');
        });

        it('filters to current workspace only', function () {
            $workspace1 = createWorkspace();
            $workspace2 = createWorkspace();

            // Create projects
            Project::withoutGlobalScope(WorkspaceScope::class)
                ->insert([
                    ['name' => 'WS1 Project', 'workspace_id' => $workspace1->id, 'created_at' => now(), 'updated_at' => now()],
                    ['name' => 'WS2 Project', 'workspace_id' => $workspace2->id, 'created_at' => now(), 'updated_at' => now()],
                ]);

            $workspace2->makeCurrent();

            expect(Project::count())->toBe(1)
                ->and(Project::first()->name)->toBe('WS2 Project');
        });

        it('returns empty when no workspace is current', function () {
            $workspace = createWorkspace();

            Project::withoutGlobalScope(WorkspaceScope::class)
                ->create(['name' => 'Some Project', 'workspace_id' => $workspace->id]);

            // Make sure no workspace is current
            $workspace->forgetCurrent();

            // Should return nothing (impossible condition)
            expect(Project::count())->toBe(0);
        });

        it('throws exception when configured and no workspace', function () {
            config()->set('workspaces.scope.throw_when_missing', true);

            $workspace = createWorkspace();
            $workspace->forgetCurrent();

            expect(fn () => Project::all())
                ->toThrow(NoCurrentWorkspaceException::class);
        });
    });

    describe('Auto-assignment on Create', function () {
        it('auto-assigns workspace_id on create', function () {
            $workspace = createWorkspace();
            $workspace->makeCurrent();

            $project = Project::create(['name' => 'Auto Assigned']);

            expect($project->workspace_id)->toBe($workspace->id);
        });

        it('does not override if workspace_id is set', function () {
            $workspace1 = createWorkspace();
            $workspace2 = createWorkspace();

            $workspace1->makeCurrent();

            // Explicitly set workspace_id to workspace2
            $project = Project::withoutGlobalScope(WorkspaceScope::class)
                ->create(['name' => 'Explicit', 'workspace_id' => $workspace2->id]);

            expect($project->workspace_id)->toBe($workspace2->id);
        });
    });

    describe('Workspace Relationship', function () {
        it('has workspace relationship', function () {
            $workspace = createWorkspace(['name' => 'Test Workspace']);
            $workspace->makeCurrent();

            $project = Project::create(['name' => 'Test Project']);

            expect($project->workspace)->not->toBeNull()
                ->and($project->workspace->name)->toBe('Test Workspace');
        });
    });

    describe('Query Scopes', function () {
        it('can query without workspace scope', function () {
            $workspace1 = createWorkspace();
            $workspace2 = createWorkspace();

            $workspace1->makeCurrent();
            Project::create(['name' => 'Project 1']);

            $workspace2->makeCurrent();
            Project::create(['name' => 'Project 2']);

            // Query without scope should return all
            $allProjects = Project::withoutWorkspaceScope()->get();

            expect($allProjects)->toHaveCount(2);
        });

        it('can query for specific workspace', function () {
            $workspace1 = createWorkspace();
            $workspace2 = createWorkspace();

            $workspace1->makeCurrent();
            Project::create(['name' => 'Project 1']);

            $workspace2->makeCurrent();
            Project::create(['name' => 'Project 2']);

            // Query for workspace1 while workspace2 is current
            $ws1Projects = Project::forWorkspace($workspace1)->get();

            expect($ws1Projects)->toHaveCount(1)
                ->and($ws1Projects->first()->name)->toBe('Project 1');
        });

        it('can query for workspace by id', function () {
            $workspace = createWorkspace();
            $workspace->makeCurrent();
            Project::create(['name' => 'Test Project']);

            $workspace->forgetCurrent();

            $projects = Project::forWorkspace($workspace->id)->get();

            expect($projects)->toHaveCount(1);
        });

        it('can query all workspaces', function () {
            $workspace1 = createWorkspace();
            $workspace2 = createWorkspace();

            $workspace1->makeCurrent();
            Project::create(['name' => 'Project 1']);

            $workspace2->makeCurrent();
            Project::create(['name' => 'Project 2']);

            $allProjects = Project::allWorkspaces()->get();

            expect($allProjects)->toHaveCount(2);
        });
    });

    describe('Query Builder Macros', function () {
        it('has withoutWorkspace macro', function () {
            $workspace = createWorkspace();
            $workspace->makeCurrent();

            Project::create(['name' => 'Test']);

            $query = Project::query()->withoutWorkspace();

            expect($query->toSql())->not->toContain('workspace_id');
        });

        it('has forWorkspace macro', function () {
            $workspace1 = createWorkspace();
            $workspace2 = createWorkspace();

            $workspace1->makeCurrent();
            Project::create(['name' => 'P1']);

            $workspace2->makeCurrent();
            Project::create(['name' => 'P2']);

            // Use macro to query workspace1
            $projects = Project::query()->forWorkspace($workspace1)->get();

            expect($projects)->toHaveCount(1)
                ->and($projects->first()->name)->toBe('P1');
        });

        it('has allWorkspaces macro', function () {
            $workspace1 = createWorkspace();
            $workspace2 = createWorkspace();

            $workspace1->makeCurrent();
            Project::create(['name' => 'P1']);

            $workspace2->makeCurrent();
            Project::create(['name' => 'P2']);

            $all = Project::query()->allWorkspaces()->get();

            expect($all)->toHaveCount(2);
        });
    });
});
