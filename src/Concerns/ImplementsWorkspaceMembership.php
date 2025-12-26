<?php

declare(strict_types=1);

namespace Climactic\Workspaces\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Symfony\Component\Uid\Ulid;

/**
 * Trait to be used on custom WorkspaceMembership models.
 *
 * Provides all the functionality required by WorkspaceMembershipContract.
 */
trait ImplementsWorkspaceMembership
{
    /**
     * Boot the trait.
     */
    public static function bootImplementsWorkspaceMembership(): void
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
        });
    }

    /**
     * Get the workspace this membership belongs to.
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(
            config('workspaces.models.workspace'),
            'workspace_id'
        );
    }

    /**
     * Get the user this membership belongs to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(
            config('workspaces.user_model'),
            'user_id'
        );
    }

    /**
     * Check if this membership has owner role.
     */
    public function isOwner(): bool
    {
        return $this->role === config('workspaces.owner_role', 'owner');
    }

    /**
     * Check if this membership has admin role.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Check if this membership has member role.
     */
    public function isMember(): bool
    {
        return $this->role === 'member';
    }

    /**
     * Check if this membership has a specific role.
     */
    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    /**
     * Check if this membership has a specific permission.
     */
    public function hasPermission(string $permission): bool
    {
        $permissions = $this->getPermissions();

        return in_array('*', $permissions, true) || in_array($permission, $permissions, true);
    }

    /**
     * Get all permissions for this membership.
     *
     * @return array<int, string>
     */
    public function getPermissions(): array
    {
        // First check custom permissions on the membership (cast to array in model)
        if (! empty($this->permissions)) {
            /** @var array<int, string> */
            return $this->permissions;
        }

        // Fall back to role-based permissions
        /** @var array{permissions?: array<int, string>}|null $roleConfig */
        $roleConfig = config("workspaces.roles.{$this->role}");

        return $roleConfig['permissions'] ?? [];
    }

    /**
     * Get the role name.
     */
    public function getRoleName(): string
    {
        $roleConfig = config("workspaces.roles.{$this->role}");

        return $roleConfig['name'] ?? ucfirst($this->role);
    }

    /**
     * The accessors to append to the model's array form.
     */
    protected function getArrayableAppends(): array
    {
        return array_merge(parent::getArrayableAppends(), ['permissions_list']);
    }

    /**
     * Get permissions as an attribute.
     */
    public function getPermissionsListAttribute(): array
    {
        return $this->getPermissions();
    }

    /**
     * Check if this is the user's current workspace.
     */
    public function isCurrent(): bool
    {
        return (bool) $this->is_current;
    }

    /**
     * Make this the user's current workspace.
     */
    public function makeCurrent(): void
    {
        $membershipModel = config('workspaces.models.membership');

        // Clear current flag from all memberships for this user
        $membershipModel::where('user_id', $this->user_id)
            ->where('is_current', true)
            ->update(['is_current' => false]);

        // Set current flag on this membership
        $this->update(['is_current' => true]);
    }
}
