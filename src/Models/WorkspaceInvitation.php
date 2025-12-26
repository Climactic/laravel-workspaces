<?php

declare(strict_types=1);

namespace Climactic\Workspaces\Models;

use Climactic\Workspaces\Concerns\ImplementsWorkspaceInvitation;
use Climactic\Workspaces\Contracts\WorkspaceInvitationContract;
use Climactic\Workspaces\Database\Factories\WorkspaceInvitationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int|string $id
 * @property int|string $workspace_id
 * @property string $email
 * @property string $role
 * @property string $token
 * @property int|string|null $invited_by
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property \Illuminate\Support\Carbon|null $accepted_at
 * @property \Illuminate\Support\Carbon|null $declined_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Climactic\Workspaces\Models\Workspace $workspace
 * @property-read \Illuminate\Database\Eloquent\Model|null $inviter
 */
class WorkspaceInvitation extends Model implements WorkspaceInvitationContract
{
    use HasFactory;
    use ImplementsWorkspaceInvitation;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'workspace_id',
        'email',
        'role',
        'token',
        'invited_by',
        'expires_at',
        'accepted_at',
        'declined_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
        'declined_at' => 'datetime',
    ];

    /**
     * Get the table associated with the model.
     */
    public function getTable(): string
    {
        return config('workspaces.tables.invitations', 'workspace_invitations');
    }

    /**
     * Get the primary key type.
     */
    public function getKeyType(): string
    {
        $keyType = config('workspaces.primary_key_type', 'id');

        return in_array($keyType, ['uuid', 'ulid']) ? 'string' : 'int';
    }

    /**
     * Indicates if the IDs are auto-incrementing.
     */
    public function getIncrementing(): bool
    {
        return config('workspaces.primary_key_type', 'id') === 'id';
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): WorkspaceInvitationFactory
    {
        return WorkspaceInvitationFactory::new();
    }
}
