<?php

declare(strict_types=1);

namespace Climactic\Workspaces\Middleware;

use Climactic\Workspaces\Exceptions\NoCurrentWorkspaceException;
use Climactic\Workspaces\Exceptions\WorkspaceAccessDeniedException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWorkspaceAccess
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $containerKey = config('workspaces.container_key', 'currentWorkspace');
        $workspace = app($containerKey);

        if (! $workspace) {
            throw new NoCurrentWorkspaceException('No workspace context is set.');
        }

        $user = $request->user();

        if (! $user) {
            throw new WorkspaceAccessDeniedException('Authentication required to access this workspace.');
        }

        if (! $workspace->hasUser($user)) {
            throw new WorkspaceAccessDeniedException('You do not have access to this workspace.');
        }

        return $next($request);
    }
}
