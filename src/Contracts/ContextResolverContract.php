<?php

declare(strict_types=1);

namespace Climactic\Workspaces\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

interface ContextResolverContract
{
    /**
     * Resolve the current workspace from the request.
     *
     * @return (WorkspaceContract&Model)|null
     */
    public function resolve(Request $request): ?WorkspaceContract;
}
