<?php

declare(strict_types=1);

namespace Climactic\Workspaces\Concerns;

use Climactic\Workspaces\Scopes\WorkspaceScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Trait to be used on models that belong to a workspace.
 *
 * Applies a global scope to filter by current workspace and
 * automatically sets workspace_id on creating.
 */
trait BelongsToWorkspace
{
    /**
     * Boot the trait.
     */
    public static function bootBelongsToWorkspace(): void
    {
        // Apply global scope to filter by current workspace
        static::addGlobalScope(new WorkspaceScope);

        // Auto-set workspace_id on creating
        static::creating(function (Model $model) {
            if (empty($model->workspace_id)) {
                $containerKey = config('workspaces.container_key', 'currentWorkspace');
                $currentWorkspace = app($containerKey);

                if ($currentWorkspace) {
                    $model->workspace_id = $currentWorkspace->getKey();
                }
            }
        });
    }

    /**
     * Get the workspace this model belongs to.
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(
            config('workspaces.models.workspace'),
            'workspace_id'
        );
    }

    /**
     * Scope to query without the workspace scope.
     */
    public function scopeWithoutWorkspaceScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope(WorkspaceScope::class);
    }

    /**
     * Scope to filter by a specific workspace.
     */
    public function scopeForWorkspace(Builder $query, Model|string|int $workspace): Builder
    {
        $workspaceId = $workspace instanceof Model
            ? $workspace->getKey()
            : $workspace;

        return $query->withoutGlobalScope(WorkspaceScope::class)
            ->where($this->qualifyColumn('workspace_id'), $workspaceId);
    }

    /**
     * Scope to include all workspaces.
     */
    public function scopeAllWorkspaces(Builder $query): Builder
    {
        return $query->withoutGlobalScope(WorkspaceScope::class);
    }
}
