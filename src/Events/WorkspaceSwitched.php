<?php

declare(strict_types=1);

namespace Climactic\Workspaces\Events;

use Climactic\Workspaces\Contracts\WorkspaceContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class WorkspaceSwitched
{
    use Dispatchable;
    use SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Model $user,
        public WorkspaceContract|Model|string|int $workspace,
        public string|int|null $previousWorkspaceId = null
    ) {}
}
