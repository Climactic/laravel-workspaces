<?php

declare(strict_types=1);

namespace Climactic\Workspaces\Middleware;

use Climactic\Workspaces\ContextResolvers\ChainResolver;
use Climactic\Workspaces\Permissions\PermissionManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetWorkspaceContext
{
    public function __construct(
        protected PermissionManager $permissionManager
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $resolver = ChainResolver::fromConfig();
        $workspace = $resolver->resolve($request);

        if ($workspace) {
            $workspace->makeCurrent();

            // Set workspace context for permission checking
            $this->permissionManager->setWorkspaceContext($workspace);
        }

        return $next($request);
    }
}
