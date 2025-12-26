<?php

declare(strict_types=1);

namespace Climactic\Workspaces\Database\Factories;

use Climactic\Workspaces\Models\WorkspaceInvitation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WorkspaceInvitation>
 */
class WorkspaceInvitationFactory extends Factory
{
    protected $model = WorkspaceInvitation::class;

    /**
     * Define the model's default state.
     *
     * @return array{workspace_id: null, email: string, role: string, token: string, invited_by: null, expires_at: \Illuminate\Support\Carbon, accepted_at: null, declined_at: null}
     */
    public function definition(): array
    {
        return [
            'workspace_id' => null,
            'email' => fake()->unique()->safeEmail(),
            'role' => config('workspaces.default_role', 'member'),
            'token' => Str::random(64),
            'invited_by' => null,
            'expires_at' => now()->addDays(config('workspaces.invitations.expires_after_days', 7)),
            'accepted_at' => null,
            'declined_at' => null,
        ];
    }

    /**
     * Mark the invitation as accepted.
     */
    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'accepted_at' => now(),
        ]);
    }

    /**
     * Mark the invitation as declined.
     */
    public function declined(): static
    {
        return $this->state(fn (array $attributes) => [
            'declined_at' => now(),
        ]);
    }

    /**
     * Mark the invitation as expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subDay(),
        ]);
    }

    /**
     * Set the role for the invitation.
     */
    public function withRole(string $role): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => $role,
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
     * Set the inviter.
     */
    public function invitedBy($user): static
    {
        return $this->state(fn (array $attributes) => [
            'invited_by' => is_object($user) ? $user->getKey() : $user,
        ]);
    }
}
