<?php

declare(strict_types=1);

namespace Climactic\Workspaces\Exceptions;

class InvitationExpiredException extends WorkspaceException
{
    /**
     * Create a new exception instance.
     */
    public function __construct(string $message = 'This invitation has expired.')
    {
        parent::__construct($message);
    }
}
