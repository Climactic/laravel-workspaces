<?php

declare(strict_types=1);

namespace Climactic\Workspaces\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Symfony\Component\Uid\Ulid;

/**
 * Trait to be used on custom WorkspaceInvitation models.
 *
 * Provides all the functionality required by WorkspaceInvitationContract.
 */
trait ImplementsWorkspaceInvitation
{
    /**
     * Boot the trait.
     */
    public static function bootImplementsWorkspaceInvitation(): void
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
     * Get the workspace this invitation belongs to.
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(
            config('workspaces.models.workspace'),
            'workspace_id'
        );
    }

    /**
     * Get the user who sent this invitation.
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(
            config('workspaces.user_model'),
            'invited_by'
        );
    }

    /**
     * Check if this invitation is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Check if this invitation has been accepted.
     */
    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    /**
     * Check if this invitation has been declined.
     */
    public function isDeclined(): bool
    {
        return $this->declined_at !== null;
    }

    /**
     * Check if this invitation is valid (not expired, not accepted, not declined).
     */
    public function isValid(): bool
    {
        return ! $this->isExpired() && ! $this->isAccepted() && ! $this->isDeclined();
    }

    /**
     * Check if this invitation is pending (valid and not yet acted upon).
     */
    public function isPending(): bool
    {
        return $this->isValid();
    }

    /**
     * Mark this invitation as accepted.
     */
    public function markAsAccepted(): void
    {
        $this->update(['accepted_at' => now()]);
    }

    /**
     * Mark this invitation as declined.
     */
    public function markAsDeclined(): void
    {
        $this->update(['declined_at' => now()]);
    }

    /**
     * Generate a unique invitation token.
     *
     * Uses ULID for guaranteed uniqueness without race conditions.
     */
    public static function generateToken(): string
    {
        // ULID provides:
        // - Guaranteed uniqueness (timestamp + randomness)
        // - No race conditions (no DB lookup needed)
        // - Chronological ordering
        // - URL-safe characters
        return (string) Str::ulid();
    }

    /**
     * Scope for pending invitations.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('accepted_at')
            ->whereNull('declined_at')
            ->where('expires_at', '>', now());
    }

    /**
     * Scope for expired invitations.
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->whereNull('accepted_at')
            ->whereNull('declined_at')
            ->where('expires_at', '<=', now());
    }

    /**
     * Scope for accepted invitations.
     */
    public function scopeAccepted(Builder $query): Builder
    {
        return $query->whereNotNull('accepted_at');
    }

    /**
     * Scope for declined invitations.
     */
    public function scopeDeclined(Builder $query): Builder
    {
        return $query->whereNotNull('declined_at');
    }

    /**
     * Scope by email address.
     */
    public function scopeForEmail(Builder $query, string $email): Builder
    {
        return $query->where('email', $email);
    }

    /**
     * Get the acceptance URL for this invitation.
     */
    public function getAcceptanceUrl(): string
    {
        $urlPattern = config('workspaces.invitations.acceptance_url', '/workspace-invitations/{token}/accept');

        return url(str_replace('{token}', $this->token, $urlPattern));
    }

    /**
     * Get the role name for display.
     */
    public function getRoleName(): string
    {
        $roleConfig = config("workspaces.roles.{$this->role}");

        return $roleConfig['name'] ?? ucfirst($this->role);
    }
}
