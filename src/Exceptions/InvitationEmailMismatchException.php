<?php

declare(strict_types=1);

namespace Climactic\Workspaces\Exceptions;

final class InvitationEmailMismatchException extends WorkspaceException
{
    /**
     * Create a new exception instance.
     */
    public function __construct(string $message = 'This invitation was sent to a different email address.')
    {
        parent::__construct($message);
    }
}
