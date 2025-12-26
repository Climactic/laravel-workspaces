<?php

declare(strict_types=1);

namespace Climactic\Workspaces\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

class WorkspaceAccessDeniedException extends HttpException
{
    /**
     * Create a new exception instance.
     */
    public function __construct(string $message = 'Access denied to this workspace.')
    {
        parent::__construct(403, $message);
    }
}
