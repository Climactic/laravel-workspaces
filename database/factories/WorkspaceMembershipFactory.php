<?php

declare(strict_types=1);

namespace Climactic\Workspaces\Database\Factories;

use Climactic\Workspaces\Models\WorkspaceMembership;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkspaceMembership>
 */
class WorkspaceMembershipFactory extends Factory
{
    protected $model = WorkspaceMembership::class;

    /**
     * Define the model's default state.
     *
     * @return array{workspace_id: null, user_id: null, role: string, permissions: null, is_current: bool, joined_at: \Illuminate\Support\Carbon}
     */
    public function definition(): array
    {
        return [
            'workspace_id' => null,
            'user_id' => null,
            'role' => config('workspaces.default_role', 'member'),
            'permissions' => null,
            'is_current' => false,
            'joined_at' => now(),
        ];
    }

    /**
     * Set the membership role to owner.
     */
    public function owner(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => config('workspaces.owner_role', 'owner'),
        ]);
    }

    /**
     * Set the membership role to admin.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'admin',
        ]);
    }

    /**
     * Set the membership role to member.
     */
    public function member(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'member',
        ]);
    }

    /**
     * Set custom permissions.
     */
    public function withPermissions(array $permissions): static
    {
        return $this->state(fn (array $attributes) => [
            'permissions' => $permissions,
        ]);
    }

    /**
     * Set the workspace.
     */
    public function forWorkspace($workspace): static
    {
        return $this->state(fn (array $attributes) => [
            'workspace_id' => is_object($workspace) ? $workspace->getKey() : $workspace,
        ]);
    }

    /**
     * Set the user.
     */
    public function forUser($user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => is_object($user) ? $user->getKey() : $user,
        ]);
    }

    /**
     * Mark this membership as the user's current workspace.
     */
    public function current(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_current' => true,
        ]);
    }
}
