<?php

declare(strict_types=1);

namespace Climactic\Workspaces\ContextResolvers;

use Climactic\Workspaces\Contracts\WorkspaceContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Resolves workspace from the session.
 */
class SessionResolver extends ContextResolver
{
    /**
     * Resolve the current workspace from the request.
     *
     * @return (WorkspaceContract&Model)|null
     */
    public function resolve(Request $request): ?WorkspaceContract
    {
        $sessionKey = config('workspaces.context.session.key', 'current_workspace_id');

        $workspaceId = $request->session()->get($sessionKey);

        if (! $workspaceId) {
            return null;
        }

        $workspace = $this->findById($workspaceId);

        // Optionally verify the user has access to this workspace
        $user = $request->user();
        if ($workspace && $user && method_exists($user, 'belongsToWorkspace')) {
            if (! $user->belongsToWorkspace($workspace)) {
                // Clear invalid workspace from session
                $request->session()->forget($sessionKey);

                return null;
            }
        }

        return $workspace;
    }
}
