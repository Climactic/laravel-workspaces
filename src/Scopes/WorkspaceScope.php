<?php

declare(strict_types=1);

namespace Climactic\Workspaces\Scopes;

use Climactic\Workspaces\Exceptions\NoCurrentWorkspaceException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class WorkspaceScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $containerKey = config('workspaces.container_key', 'currentWorkspace');
        $workspace = app($containerKey);

        if ($workspace) {
            $builder->where(
                $model->qualifyColumn('workspace_id'),
                $workspace->getKey()
            );
        } elseif (config('workspaces.scope.throw_when_missing', false)) {
            throw new NoCurrentWorkspaceException(
                'No workspace context is set. Cannot query workspace-scoped models.'
            );
        }
        // If no workspace and not throwing, the query will return no results
        // because workspace_id won't match anything (effectively filtering everything out)
        // To prevent returning all records, we add an impossible condition
        elseif (! $workspace) {
            $builder->whereRaw('1 = 0');
        }
    }

    /**
     * Extend the query builder with the needed functions.
     */
    public function extend(Builder $builder): void
    {
        $builder->macro('withoutWorkspace', function (Builder $builder) {
            return $builder->withoutGlobalScope(WorkspaceScope::class);
        });

        $builder->macro('forWorkspace', function (Builder $builder, $workspace) {
            $workspaceId = $workspace instanceof Model ? $workspace->getKey() : $workspace;

            return $builder->withoutGlobalScope(WorkspaceScope::class)
                ->where('workspace_id', $workspaceId);
        });

        $builder->macro('allWorkspaces', function (Builder $builder) {
            return $builder->withoutGlobalScope(WorkspaceScope::class);
        });
    }
}
