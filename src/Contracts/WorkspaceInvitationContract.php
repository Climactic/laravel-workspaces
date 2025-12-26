<?php

declare(strict_types=1);

namespace Climactic\Workspaces\Contracts;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

interface WorkspaceInvitationContract
{
    /**
     * Get the workspace this invitation belongs to.
     */
    public function workspace(): BelongsTo;

    /**
     * Get the user who sent this invitation.
     */
    public function inviter(): BelongsTo;

    /**
     * Check if this invitation is expired.
     */
    public function isExpired(): bool;

    /**
     * Check if this invitation has been accepted.
     */
    public function isAccepted(): bool;

    /**
     * Check if this invitation has been declined.
     */
    public function isDeclined(): bool;

    /**
     * Check if this invitation is valid (not expired, not accepted, not declined).
     */
    public function isValid(): bool;

    /**
     * Mark this invitation as accepted.
     */
    public function markAsAccepted(): void;

    /**
     * Mark this invitation as declined.
     */
    public function markAsDeclined(): void;

    /**
     * Generate a unique invitation token.
     */
    public static function generateToken(): string;

    /**
     * Get the display name for the role.
     */
    public function getRoleName(): string;

    /**
     * Get the URL for accepting this invitation.
     */
    public function getAcceptanceUrl(): string;
}
