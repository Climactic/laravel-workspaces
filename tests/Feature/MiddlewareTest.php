<?php

use Climactic\Workspaces\Exceptions\NoCurrentWorkspaceException;
use Climactic\Workspaces\Exceptions\WorkspaceAccessDeniedException;
use Climactic\Workspaces\Middleware\EnsureWorkspaceAccess;
use Climactic\Workspaces\Middleware\EnsureWorkspaceRole;
use Climactic\Workspaces\Middleware\SetWorkspaceContext;
use Climactic\Workspaces\Models\Workspace;
use Climactic\Workspaces\Permissions\PermissionManager;
use Illuminate\Http\Request;

describe('SetWorkspaceContext Middleware', function () {
    it('sets current workspace from authenticated user', function () {
        $user = createUser();
        $workspace = createWorkspace(['name' => 'Test Workspace']);
        $workspace->addMember($user, 'owner', setAsCurrent: true);

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => $user);

        $middleware = new SetWorkspaceContext(app(PermissionManager::class));
        $response = $middleware->handle($request, fn () => response('OK'));

        expect(Workspace::current())->not->toBeNull()
            ->and(Workspace::current()->name)->toBe('Test Workspace');
    });

    it('continues without setting workspace when user has no current workspace', function () {
        $user = createUser();

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => $user);

        $middleware = new SetWorkspaceContext(app(PermissionManager::class));
        $response = $middleware->handle($request, fn () => response('OK'));

        expect(Workspace::checkCurrent())->toBeFalse();
        expect($response->getContent())->toBe('OK');
    });

    it('continues without setting workspace for guest', function () {
        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => null);

        $middleware = new SetWorkspaceContext(app(PermissionManager::class));
        $response = $middleware->handle($request, fn () => response('OK'));

        expect(Workspace::checkCurrent())->toBeFalse();
        expect($response->getContent())->toBe('OK');
    });
});

describe('EnsureWorkspaceAccess Middleware', function () {
    it('allows access when user is workspace member', function () {
        $user = createUser();
        $workspace = createWorkspace();
        $workspace->addMember($user, 'member', setAsCurrent: true);
        $workspace->makeCurrent();

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => $user);

        $middleware = new EnsureWorkspaceAccess;
        $response = $middleware->handle($request, fn () => response('OK'));

        expect($response->getContent())->toBe('OK');
    });

    it('denies access when user is not workspace member', function () {
        $user = createUser();
        $otherUser = createUser();
        $workspace = createWorkspace();
        $workspace->addMember($otherUser, 'owner');
        $workspace->makeCurrent();

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => $user);

        $middleware = new EnsureWorkspaceAccess;

        expect(fn () => $middleware->handle($request, fn () => response('OK')))
            ->toThrow(WorkspaceAccessDeniedException::class);
    });

    it('throws exception when no current workspace', function () {
        $user = createUser();

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => $user);

        $middleware = new EnsureWorkspaceAccess;

        expect(fn () => $middleware->handle($request, fn () => response('OK')))
            ->toThrow(NoCurrentWorkspaceException::class);
    });
});

describe('EnsureWorkspaceRole Middleware', function () {
    it('allows access when user has required role', function () {
        $user = createUser();
        $workspace = createWorkspace();
        $workspace->addMember($user, 'admin', setAsCurrent: true);
        $workspace->makeCurrent();

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => $user);

        $middleware = new EnsureWorkspaceRole;
        $response = $middleware->handle($request, fn () => response('OK'), 'admin');

        expect($response->getContent())->toBe('OK');
    });

    it('allows access when user has one of required roles', function () {
        $user = createUser();
        $workspace = createWorkspace();
        $workspace->addMember($user, 'owner', setAsCurrent: true);
        $workspace->makeCurrent();

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => $user);

        $middleware = new EnsureWorkspaceRole;
        $response = $middleware->handle($request, fn () => response('OK'), 'admin', 'owner');

        expect($response->getContent())->toBe('OK');
    });

    it('denies access when user does not have required role', function () {
        $user = createUser();
        $workspace = createWorkspace();
        $workspace->addMember($user, 'member', setAsCurrent: true);
        $workspace->makeCurrent();

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => $user);

        $middleware = new EnsureWorkspaceRole;

        expect(fn () => $middleware->handle($request, fn () => response('OK'), 'admin'))
            ->toThrow(WorkspaceAccessDeniedException::class);
    });

    it('throws exception when no current workspace', function () {
        $user = createUser();

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => $user);

        $middleware = new EnsureWorkspaceRole;

        expect(fn () => $middleware->handle($request, fn () => response('OK'), 'admin'))
            ->toThrow(NoCurrentWorkspaceException::class);
    });
});

describe('Middleware Route Integration', function () {
    it('workspace middleware alias is registered', function () {
        // The middleware aliases are registered in the service provider
        // We can verify they work by checking the router
        $router = app(\Illuminate\Routing\Router::class);
        $aliases = $router->getMiddleware();

        expect($aliases)->toHaveKey('workspace')
            ->and($aliases)->toHaveKey('workspace.access')
            ->and($aliases)->toHaveKey('workspace.role');
    });
});
