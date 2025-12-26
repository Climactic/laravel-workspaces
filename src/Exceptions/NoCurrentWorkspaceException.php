<?php

declare(strict_types=1);

namespace Climactic\Workspaces\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

class NoCurrentWorkspaceException extends HttpException
{
    /**
     * Create a new exception instance.
     */
    public function __construct(string $message = 'No workspace context is set.')
    {
        parent::__construct(400, $message);
    }
}
