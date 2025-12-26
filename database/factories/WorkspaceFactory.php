<?php

declare(strict_types=1);

namespace Climactic\Workspaces\Database\Factories;

use Climactic\Workspaces\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Workspace>
 */
class WorkspaceFactory extends Factory
{
    protected $model = Workspace::class;

    /**
     * Define the model's default state.
     *
     * @return array{name: string, slug: string, description: ?string, owner_id: null, settings: array<mixed>, personal: bool}
     */
    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(5),
            'description' => fake()->optional()->sentence(),
            'owner_id' => null,
            'settings' => [],
            'personal' => false,
        ];
    }

    /**
     * Mark the workspace as personal.
     */
    public function personal(): static
    {
        return $this->state(fn (array $attributes) => [
            'personal' => true,
        ]);
    }

    /**
     * Set the workspace owner.
     */
    public function withOwner($user): static
    {
        return $this->state(fn (array $attributes) => [
            'owner_id' => is_object($user) ? $user->getKey() : $user,
        ]);
    }

    /**
     * Set workspace settings.
     */
    public function withSettings(array $settings): static
    {
        return $this->state(fn (array $attributes) => [
            'settings' => $settings,
        ]);
    }
}
