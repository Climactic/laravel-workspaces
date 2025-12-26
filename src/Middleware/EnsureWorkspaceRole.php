<?php

declare(strict_types=1);

namespace Climactic\Workspaces\Middleware;

use Climactic\Workspaces\Exceptions\NoCurrentWorkspaceException;
use Climactic\Workspaces\Exceptions\WorkspaceAccessDeniedException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWorkspaceRole
{
    /**
     * Handle an incoming request.
     *
     * @param  string  ...$roles  The roles required (any one of them)
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $containerKey = config('workspaces.container_key', 'currentWorkspace');
        $workspace = app($containerKey);

        if (! $workspace) {
            throw new NoCurrentWorkspaceException('No workspace context is set.');
        }

        $user = $request->user();

        if (! $user) {
            throw new WorkspaceAccessDeniedException('Authentication required.');
        }

        // Check if user has the HasWorkspaces trait
        if (! method_exists($user, 'hasWorkspaceRole')) {
            throw new WorkspaceAccessDeniedException(
                'User model must use the HasWorkspaces trait.'
            );
        }

        if (! $user->hasWorkspaceRole($workspace, $roles)) {
            throw new WorkspaceAccessDeniedException(
                'You do not have the required role for this action.'
            );
        }

        return $next($request);
    }
}
