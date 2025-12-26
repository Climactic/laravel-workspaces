<?php

declare(strict_types=1);

namespace Climactic\Workspaces\Listeners;

use Climactic\Workspaces\Actions\CreateWorkspace;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;

class CreateWorkspaceOnRegistration
{
    /**
     * Handle the event.
     */
    public function handle(Registered $event): void
    {
        // Check if auto-creation is enabled
        if (! config('workspaces.auto_create_on_registration.enabled', true)) {
            return;
        }

        $user = $event->user;

        // User must be an Eloquent model
        if (! $user instanceof \Illuminate\Database\Eloquent\Model) {
            return;
        }

        // Skip if user already has workspaces (shouldn't happen on registration, but just in case)
        if (method_exists($user, 'workspaces') && $user->workspaces()->count() > 0) {
            return;
        }

        DB::transaction(function () use ($user): void {
            $nameFrom = config('workspaces.auto_create_on_registration.name_from', 'name');
            $nameSuffix = config('workspaces.auto_create_on_registration.name_suffix', "'s Workspace");

            $userName = $user->getAttribute($nameFrom) ?? 'User';
            $workspaceName = $userName.$nameSuffix;

            /** @var CreateWorkspace $createAction */
            $createAction = app(config('workspaces.actions.create_workspace'));

            // Create workspace with is_current flag set to true for the owner
            $createAction->execute([
                'name' => $workspaceName,
                'personal' => true,
            ], $user, setAsCurrent: true);
        });
    }
}
