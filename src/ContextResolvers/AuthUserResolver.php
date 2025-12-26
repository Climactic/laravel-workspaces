<?php

declare(strict_types=1);

namespace Climactic\Workspaces\ContextResolvers;

use Climactic\Workspaces\Contracts\WorkspaceContract;
use Illuminate\Http\Request;

/**
 * Resolves workspace from the authenticated user's current workspace membership.
 *
 * Uses the is_current column in the workspace_memberships pivot table
 * to determine the user's current workspace without modifying the users table.
 */
class AuthUserResolver extends ContextResolver
{
    /**
     * Resolve the current workspace from the request.
     */
    public function resolve(Request $request): ?WorkspaceContract
    {
        $user = $request->user();

        if (! $user) {
            return null;
        }

        // Check if user has the currentWorkspace method (from HasWorkspaces trait)
        if (! method_exists($user, 'currentWorkspace')) {
            return null;
        }

        $workspace = $user->currentWorkspace();

        if (! $workspace) {
            return null;
        }

        return $workspace;
    }
}
