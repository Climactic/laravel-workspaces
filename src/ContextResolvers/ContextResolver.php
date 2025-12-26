<?php

declare(strict_types=1);

namespace Climactic\Workspaces\ContextResolvers;

use Climactic\Workspaces\Contracts\ContextResolverContract;
use Climactic\Workspaces\Contracts\WorkspaceContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

abstract class ContextResolver implements ContextResolverContract
{
    /**
     * Resolve the current workspace from the request.
     *
     * @return (WorkspaceContract&Model)|null
     */
    abstract public function resolve(Request $request): ?WorkspaceContract;

    /**
     * Get the configured workspace model class.
     */
    protected function getWorkspaceModel(): string
    {
        return config('workspaces.models.workspace');
    }

    /**
     * Find a workspace by its ID.
     *
     * @return (WorkspaceContract&Model)|null
     */
    protected function findById(string|int $id): ?WorkspaceContract
    {
        return $this->getWorkspaceModel()::find($id);
    }

    /**
     * Find a workspace by a specific field.
     *
     * @return (WorkspaceContract&Model)|null
     */
    protected function findBy(string $field, mixed $value): ?WorkspaceContract
    {
        return $this->getWorkspaceModel()::where($field, $value)->first();
    }
}
