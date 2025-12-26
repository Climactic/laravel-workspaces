<?php

declare(strict_types=1);

namespace Climactic\Workspaces\ContextResolvers;

use Climactic\Workspaces\Contracts\WorkspaceContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Resolves workspace from a route parameter.
 */
class RouteParameterResolver extends ContextResolver
{
    /**
     * Resolve the current workspace from the request.
     *
     * @return (WorkspaceContract&Model)|null
     */
    public function resolve(Request $request): ?WorkspaceContract
    {
        $paramName = config('workspaces.context.route_parameter.name', 'workspace');
        $field = config('workspaces.context.route_parameter.field', 'slug');

        $paramValue = $request->route($paramName);

        if (! $paramValue) {
            return null;
        }

        // If the route already resolved the workspace model
        if ($paramValue instanceof WorkspaceContract && $paramValue instanceof Model) {
            return $paramValue;
        }

        // If it's a model but not a workspace (shouldn't happen but just in case)
        if (is_object($paramValue)) {
            return null;
        }

        return $this->findBy($field, $paramValue);
    }
}
