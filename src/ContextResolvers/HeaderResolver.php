<?php

declare(strict_types=1);

namespace Climactic\Workspaces\ContextResolvers;

use Climactic\Workspaces\Contracts\WorkspaceContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Resolves workspace from a request header (useful for APIs).
 */
class HeaderResolver extends ContextResolver
{
    /**
     * Resolve the current workspace from the request.
     *
     * @return (WorkspaceContract&Model)|null
     */
    public function resolve(Request $request): ?WorkspaceContract
    {
        $headerName = config('workspaces.context.header.name', 'X-Workspace-Id');

        $workspaceId = $request->header($headerName);

        if (! $workspaceId || ! is_string($workspaceId)) {
            return null;
        }

        $workspace = $this->findById($workspaceId);

        if (! $workspace) {
            return null;
        }

        // Verify the authenticated user has access to this workspace
        $user = $request->user();
        if ($user && method_exists($user, 'belongsToWorkspace')) {
            if (! $user->belongsToWorkspace($workspace)) {
                return null;
            }
        }

        return $workspace;
    }
}
