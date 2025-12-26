<?php

declare(strict_types=1);

namespace Climactic\Workspaces\Concerns;

use Climactic\Workspaces\Events\MemberAdded;
use Climactic\Workspaces\Events\MemberRemoved;
use Climactic\Workspaces\Events\MemberRoleUpdated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Symfony\Component\Uid\Ulid;

/**
 * Trait to be used on custom Workspace models.
 *
 * Provides all the functionality required by WorkspaceContract.
 */
trait ImplementsWorkspace
{
    /**
     * Boot the trait.
     */
    public static function bootImplementsWorkspace(): void
    {
        static::creating(function (Model $model) {
            // Auto-generate UUID/ULID if configured and not provided
            $keyType = config('workspaces.primary_key_type', 'id');
            $keyName = $model->getKeyName();

            if (empty($model->{$keyName})) {
                if ($keyType === 'uuid') {
                    $model->{$keyName} = (string) Str::uuid();
                } elseif ($keyType === 'ulid') {
                    $model->{$keyName} = (string) new Ulid;
                }
            }

            // Auto-generate slug if not provided
            /** @var string|null $slug */
            $slug = $model->getAttribute('slug');
            /** @var string|null $name */
            $name = $model->getAttribute('name');
            if (empty($slug) && ! empty($name)) {
                $model->setAttribute('slug', static::generateUniqueSlug($name));
            }
        });

        // Handle slug collision on save (race condition protection)
        static::saving(function (Model $model) {
            if ($model->isDirty('slug')) {
                /** @var string $slug */
                $slug = $model->getAttribute('slug');
                $model->setAttribute('slug', static::ensureUniqueSlug($slug, $model->exists ? $model->getKey() : null));
            }
        });
    }

    /**
     * Get the workspace owner.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(config('workspaces.user_model'), 'owner_id');
    }

    /**
     * Get all memberships for this workspace.
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(
            config('workspaces.models.membership'),
            'workspace_id'
        );
    }

    /**
     * Get all members of this workspace.
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(
            config('workspaces.user_model'),
            config('workspaces.tables.memberships', 'workspace_memberships'),
            'workspace_id',
            'user_id'
        )->withPivot(['role', 'permissions', 'is_current', 'joined_at'])
            ->withTimestamps();
    }

    /**
     * Get all invitations for this workspace.
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(
            config('workspaces.models.invitation'),
            'workspace_id'
        );
    }

    /**
     * Get pending invitations for this workspace.
     */
    public function pendingInvitations(): HasMany
    {
        return $this->invitations()
            ->whereNull('accepted_at')
            ->whereNull('declined_at')
            ->where('expires_at', '>', now());
    }

    /**
     * Get the current workspace from the container.
     */
    public static function current(): ?static
    {
        $containerKey = config('workspaces.container_key', 'currentWorkspace');

        return app($containerKey);
    }

    /**
     * Check if there is a current workspace.
     */
    public static function checkCurrent(): bool
    {
        return static::current() !== null;
    }

    /**
     * Check if a user is a member of this workspace.
     */
    public function hasUser(Model|string|int $user): bool
    {
        $userId = $user instanceof Model ? $user->getKey() : $user;
        $usersTable = config('workspaces.tables.users', 'users');

        return $this->members()->where("{$usersTable}.id", $userId)->exists();
    }

    /**
     * Check if a user has a specific role in this workspace.
     */
    public function hasUserWithRole(Model|string|int $user, string $role): bool
    {
        $userId = $user instanceof Model ? $user->getKey() : $user;

        return $this->memberships()
            ->where('user_id', $userId)
            ->where('role', $role)
            ->exists();
    }

    /**
     * Get the role of a user in this workspace.
     */
    public function getMemberRole(Model|string|int $user): ?string
    {
        $userId = $user instanceof Model ? $user->getKey() : $user;

        return $this->memberships()
            ->where('user_id', $userId)
            ->value('role');
    }

    /**
     * Add a member to this workspace.
     *
     * @param  Model|string|int  $user  The user to add
     * @param  string|null  $role  The role to assign
     * @param  bool  $setAsCurrent  Whether to set this as the user's current workspace
     */
    public function addMember(Model|string|int $user, ?string $role = null, bool $setAsCurrent = false): void
    {
        $role = $role ?? config('workspaces.default_role', 'member');
        $userId = $user instanceof Model ? $user->getKey() : $user;

        // If setting as current, clear the current flag from other memberships first
        if ($setAsCurrent) {
            $membershipModel = config('workspaces.models.membership');
            $membershipModel::where('user_id', $userId)
                ->where('is_current', true)
                ->update(['is_current' => false]);
        }

        // Build pivot attributes
        $pivotAttributes = [
            'role' => $role,
            'is_current' => $setAsCurrent,
            'joined_at' => now(),
        ];

        // Generate UUID/ULID for pivot table if not using auto-incrementing IDs
        $keyType = config('workspaces.primary_key_type', 'id');
        if ($keyType === 'uuid') {
            $pivotAttributes['id'] = (string) Str::uuid();
        } elseif ($keyType === 'ulid') {
            $pivotAttributes['id'] = (string) new Ulid;
        }

        $this->members()->attach($userId, $pivotAttributes);

        event(new MemberAdded($this, $user, $role));
    }

    /**
     * Remove a member from this workspace.
     */
    public function removeMember(Model|string|int $user): void
    {
        $userId = $user instanceof Model ? $user->getKey() : $user;

        $this->members()->detach($userId);

        event(new MemberRemoved($this, $user));
    }

    /**
     * Update a member's role in this workspace.
     */
    public function updateMemberRole(Model|string|int $user, string $role): void
    {
        $userId = $user instanceof Model ? $user->getKey() : $user;

        $oldRole = $this->getMemberRole($user);

        $this->members()->updateExistingPivot($userId, ['role' => $role]);

        event(new MemberRoleUpdated($this, $user, $oldRole ?? '', $role));
    }

    /**
     * Check if this is a personal workspace.
     */
    public function isPersonal(): bool
    {
        return $this->personal ?? false;
    }

    /**
     * Make this workspace the current workspace.
     */
    public function makeCurrent(): static
    {
        $containerKey = config('workspaces.container_key', 'currentWorkspace');
        $contextKey = config('workspaces.context_key', 'workspace_id');

        app()->instance($containerKey, $this);

        // Also set in Laravel's Context facade if available
        if (class_exists(\Illuminate\Support\Facades\Context::class)) {
            \Illuminate\Support\Facades\Context::add($contextKey, $this->getKey());
        }

        return $this;
    }

    /**
     * Forget the current workspace from the container.
     */
    public function forgetCurrent(): static
    {
        $containerKey = config('workspaces.container_key', 'currentWorkspace');
        $contextKey = config('workspaces.context_key', 'workspace_id');

        app()->forgetInstance($containerKey);

        // Also forget from Laravel's Context facade if available
        if (class_exists(\Illuminate\Support\Facades\Context::class)) {
            \Illuminate\Support\Facades\Context::forget($contextKey);
        }

        return $this;
    }

    /**
     * Execute a callback within the context of this workspace.
     */
    public function execute(callable $callback): mixed
    {
        $containerKey = config('workspaces.container_key', 'currentWorkspace');
        $previousWorkspace = app($containerKey);

        $this->makeCurrent();

        try {
            return $callback($this);
        } finally {
            if ($previousWorkspace) {
                $previousWorkspace->makeCurrent();
            } else {
                $this->forgetCurrent();
            }
        }
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Generate a unique slug from a name.
     */
    public static function generateUniqueSlug(string $name): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $counter = 1;

        while (static::where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.Str::random(5);
            $counter++;

            if ($counter > 10) {
                $slug = $baseSlug.'-'.Str::random(8);
                break;
            }
        }

        return $slug;
    }

    /**
     * Ensure a slug is unique, appending random suffix if needed.
     *
     * This handles race conditions where the slug check passes but another
     * record takes the same slug before we save.
     */
    public static function ensureUniqueSlug(string $slug, mixed $excludeId = null): string
    {
        $query = static::where('slug', $slug);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        if (! $query->exists()) {
            return $slug;
        }

        // Slug collision - append random suffix
        return $slug.'-'.Str::random(5);
    }

    /**
     * Find a workspace by its slug.
     */
    public static function findBySlug(string $slug): ?static
    {
        /** @var static|null */
        return static::where('slug', $slug)->first();
    }

    /**
     * Find a workspace by its slug or fail.
     *
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public static function findBySlugOrFail(string $slug): static
    {
        /** @var static */
        return static::where('slug', $slug)->firstOrFail();
    }

    /**
     * Transfer ownership of this workspace to another user.
     *
     * @param  Model|string|int  $newOwner  The new owner (must be a member)
     * @param  bool  $demotePreviousOwner  Whether to demote the previous owner to admin
     *
     * @throws \InvalidArgumentException If the new owner is not a member
     */
    public function transferOwnership(Model|string|int $newOwner, bool $demotePreviousOwner = true): void
    {
        app(\Climactic\Workspaces\Actions\TransferOwnership::class)->execute(
            workspace: $this,
            newOwner: $newOwner,
            demotePreviousOwner: $demotePreviousOwner
        );
    }
}
