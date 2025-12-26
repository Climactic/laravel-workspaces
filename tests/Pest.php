<?php

use Climactic\Workspaces\Models\Workspace;
use Climactic\Workspaces\Tests\Fixtures\User;
use Climactic\Workspaces\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

/**
 * Create a user for testing.
 */
function createUser(array $attributes = []): User
{
    return User::factory()->create($attributes);
}

/**
 * Create a workspace for testing.
 */
function createWorkspace(array $attributes = [], ?User $owner = null): Workspace
{
    $owner ??= createUser();

    return Workspace::factory()->create(array_merge([
        'owner_id' => $owner->id,
    ], $attributes));
}

/**
 * Create a workspace with an owner member.
 */
function createWorkspaceWithOwner(array $workspaceAttributes = [], array $userAttributes = []): array
{
    $owner = createUser($userAttributes);
    $workspace = createWorkspace($workspaceAttributes, $owner);
    $workspace->addMember($owner, 'owner', setAsCurrent: true);

    return [$workspace, $owner];
}
