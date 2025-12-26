<?php

declare(strict_types=1);

namespace Climactic\Workspaces;

use Climactic\Workspaces\Commands\InstallCommand;
use Climactic\Workspaces\Commands\PruneInvitationsCommand;
use Climactic\Workspaces\Commands\ScaffoldCommand;
use Climactic\Workspaces\Listeners\CreateWorkspaceOnRegistration;
use Climactic\Workspaces\Permissions\PermissionManager;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class WorkspacesServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('workspaces')
            ->hasConfigFile('workspaces')
            ->hasViews()
            ->hasMigration('create_workspaces_tables')
            ->hasCommands([
                InstallCommand::class,
                PruneInvitationsCommand::class,
                ScaffoldCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        // Register null binding for current workspace
        $containerKey = config('workspaces.container_key', 'currentWorkspace');
        $this->app->bind($containerKey, fn () => null);

        // Register the main facade class as singleton
        $this->app->singleton(Workspaces::class, function ($app) {
            return new Workspaces;
        });

        // Alias for easier access
        $this->app->alias(Workspaces::class, 'workspaces');

        // Register permission manager as singleton
        $this->app->singleton(PermissionManager::class, function ($app) {
            return new PermissionManager;
        });
    }

    public function packageBooted(): void
    {
        $this->registerMiddlewareAliases();
        $this->registerEventListeners();
    }

    protected function registerMiddlewareAliases(): void
    {
        $setContext = config('workspaces.middleware.set_context');
        $ensureAccess = config('workspaces.middleware.ensure_access');
        $ensureRole = config('workspaces.middleware.ensure_role');

        if ($setContext) {
            Route::aliasMiddleware('workspace', $setContext);
        }

        if ($ensureAccess) {
            Route::aliasMiddleware('workspace.access', $ensureAccess);
        }

        if ($ensureRole) {
            Route::aliasMiddleware('workspace.role', $ensureRole);
        }
    }

    protected function registerEventListeners(): void
    {
        if (config('workspaces.auto_create_on_registration.enabled', true)) {
            $event = config(
                'workspaces.auto_create_on_registration.listen_to',
                Registered::class
            );

            Event::listen($event, CreateWorkspaceOnRegistration::class);
        }
    }
}
