<?php

declare(strict_types=1);

namespace Climactic\Workspaces\Exceptions;

class InvitationAlreadyAcceptedException extends WorkspaceException
{
    /**
     * Create a new exception instance.
     */
    public function __construct(string $message = 'This invitation has already been accepted.')
    {
        parent::__construct($message);
    }
}
