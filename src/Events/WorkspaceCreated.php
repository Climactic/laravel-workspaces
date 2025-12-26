<?php

declare(strict_types=1);

namespace Climactic\Workspaces\Events;

use Climactic\Workspaces\Contracts\WorkspaceContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class WorkspaceCreated
{
    use Dispatchable;
    use SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public WorkspaceContract|Model $workspace,
        public Model|string|int|null $owner = null
    ) {}
}
