<?php

use Climactic\Workspaces\ContextResolvers\AuthUserResolver;
use Climactic\Workspaces\ContextResolvers\ChainResolver;
use Climactic\Workspaces\ContextResolvers\HeaderResolver;
use Climactic\Workspaces\ContextResolvers\RouteParameterResolver;
use Climactic\Workspaces\ContextResolvers\SessionResolver;
use Climactic\Workspaces\ContextResolvers\SubdomainResolver;
use Climactic\Workspaces\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

describe('AuthUserResolver', function () {
    it('resolves workspace from authenticated user current workspace', function () {
        $user = createUser();
        $workspace = createWorkspace(['name' => 'User Workspace']);
        $workspace->addMember($user, 'owner', setAsCurrent: true);

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => $user);

        $resolver = new AuthUserResolver;
        $resolved = $resolver->resolve($request);

        expect($resolved)->not->toBeNull()
            ->and($resolved->id)->toBe($workspace->id);
    });

    it('returns null when user has no current workspace', function () {
        $user = createUser();

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => $user);

        $resolver = new AuthUserResolver;
        $resolved = $resolver->resolve($request);

        expect($resolved)->toBeNull();
    });

    it('returns null for unauthenticated request', function () {
        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => null);

        $resolver = new AuthUserResolver;
        $resolved = $resolver->resolve($request);

        expect($resolved)->toBeNull();
    });
});

describe('HeaderResolver', function () {
    it('resolves workspace from header', function () {
        $workspace = createWorkspace(['name' => 'Header Workspace']);

        $request = Request::create('/test', 'GET');
        $request->headers->set('X-Workspace-Id', (string) $workspace->id);

        $resolver = new HeaderResolver;
        $resolved = $resolver->resolve($request);

        expect($resolved)->not->toBeNull()
            ->and($resolved->id)->toBe($workspace->id);
    });

    it('uses configured header name', function () {
        config()->set('workspaces.context.header.name', 'X-Custom-Workspace');

        $workspace = createWorkspace();

        $request = Request::create('/test', 'GET');
        $request->headers->set('X-Custom-Workspace', (string) $workspace->id);

        $resolver = new HeaderResolver;
        $resolved = $resolver->resolve($request);

        expect($resolved)->not->toBeNull()
            ->and($resolved->id)->toBe($workspace->id);
    });

    it('returns null when header is missing', function () {
        $request = Request::create('/test', 'GET');

        $resolver = new HeaderResolver;
        $resolved = $resolver->resolve($request);

        expect($resolved)->toBeNull();
    });

    it('returns null for invalid workspace id', function () {
        $request = Request::create('/test', 'GET');
        $request->headers->set('X-Workspace-Id', '99999');

        $resolver = new HeaderResolver;
        $resolved = $resolver->resolve($request);

        expect($resolved)->toBeNull();
    });
});

describe('SessionResolver', function () {
    it('resolves workspace from session', function () {
        $workspace = createWorkspace(['name' => 'Session Workspace']);

        $request = Request::create('/test', 'GET');
        $request->setLaravelSession(app('session.store'));
        $request->session()->put('current_workspace_id', $workspace->id);

        $resolver = new SessionResolver;
        $resolved = $resolver->resolve($request);

        expect($resolved)->not->toBeNull()
            ->and($resolved->id)->toBe($workspace->id);
    });

    it('uses configured session key', function () {
        config()->set('workspaces.context.session.key', 'my_workspace');

        $workspace = createWorkspace();

        $request = Request::create('/test', 'GET');
        $request->setLaravelSession(app('session.store'));
        $request->session()->put('my_workspace', $workspace->id);

        $resolver = new SessionResolver;
        $resolved = $resolver->resolve($request);

        expect($resolved)->not->toBeNull()
            ->and($resolved->id)->toBe($workspace->id);
    });

    it('returns null when session key is missing', function () {
        $request = Request::create('/test', 'GET');
        $request->setLaravelSession(app('session.store'));

        $resolver = new SessionResolver;
        $resolved = $resolver->resolve($request);

        expect($resolved)->toBeNull();
    });
});

describe('RouteParameterResolver', function () {
    beforeEach(function () {
        Route::get('/workspace/{workspace}/test', fn () => 'OK')->name('workspace.test');
    });

    it('resolves workspace from route parameter by id', function () {
        config()->set('workspaces.context.route_parameter.field', 'id');

        $workspace = createWorkspace();

        $request = Request::create("/workspace/{$workspace->id}/test", 'GET');
        $request->setRouteResolver(function () use ($request, $workspace) {
            $route = Route::getRoutes()->match($request);
            $route->setParameter('workspace', $workspace->id);

            return $route;
        });

        $resolver = new RouteParameterResolver;
        $resolved = $resolver->resolve($request);

        expect($resolved)->not->toBeNull()
            ->and($resolved->id)->toBe($workspace->id);
    });

    it('resolves workspace from route parameter by slug', function () {
        config()->set('workspaces.context.route_parameter.field', 'slug');

        $workspace = createWorkspace(['slug' => 'my-workspace']);

        $request = Request::create('/workspace/my-workspace/test', 'GET');
        $request->setRouteResolver(function () use ($request) {
            $route = Route::getRoutes()->match($request);
            $route->setParameter('workspace', 'my-workspace');

            return $route;
        });

        $resolver = new RouteParameterResolver;
        $resolved = $resolver->resolve($request);

        expect($resolved)->not->toBeNull()
            ->and($resolved->id)->toBe($workspace->id);
    });

    it('returns null when route parameter is missing', function () {
        Route::get('/no-workspace/test', fn () => 'OK');

        $request = Request::create('/no-workspace/test', 'GET');
        $request->setRouteResolver(fn () => Route::getRoutes()->match($request));

        $resolver = new RouteParameterResolver;
        $resolved = $resolver->resolve($request);

        expect($resolved)->toBeNull();
    });
});

describe('SubdomainResolver', function () {
    beforeEach(function () {
        config()->set('workspaces.context.subdomain.domain', 'example.com');
    });

    it('resolves workspace from subdomain', function () {
        $workspace = createWorkspace(['slug' => 'acme']);

        $request = Request::create('http://acme.example.com/test', 'GET');

        $resolver = new SubdomainResolver;
        $resolved = $resolver->resolve($request);

        expect($resolved)->not->toBeNull()
            ->and($resolved->id)->toBe($workspace->id);
    });

    it('returns null for non-matching subdomain', function () {
        $request = Request::create('http://unknown.example.com/test', 'GET');

        $resolver = new SubdomainResolver;
        $resolved = $resolver->resolve($request);

        expect($resolved)->toBeNull();
    });

    it('returns null for root domain', function () {
        $request = Request::create('http://example.com/test', 'GET');

        $resolver = new SubdomainResolver;
        $resolved = $resolver->resolve($request);

        expect($resolved)->toBeNull();
    });

    it('handles www subdomain', function () {
        $request = Request::create('http://www.example.com/test', 'GET');

        $resolver = new SubdomainResolver;
        $resolved = $resolver->resolve($request);

        expect($resolved)->toBeNull();
    });
});

describe('ChainResolver', function () {
    it('tries resolvers in order until one succeeds', function () {
        $workspace = createWorkspace();

        $request = Request::create('/test', 'GET');
        $request->headers->set('X-Workspace-Id', (string) $workspace->id);
        $request->setUserResolver(fn () => null);

        config()->set('workspaces.context.resolvers', [
            AuthUserResolver::class,
            HeaderResolver::class,
        ]);

        $resolver = ChainResolver::fromConfig();
        $resolved = $resolver->resolve($request);

        // AuthUserResolver returns null (no user), HeaderResolver should succeed
        expect($resolved)->not->toBeNull()
            ->and($resolved->id)->toBe($workspace->id);
    });

    it('returns null when no resolver succeeds', function () {
        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => null);

        config()->set('workspaces.context.resolvers', [
            AuthUserResolver::class,
            HeaderResolver::class,
        ]);

        $resolver = ChainResolver::fromConfig();
        $resolved = $resolver->resolve($request);

        expect($resolved)->toBeNull();
    });

    it('stops at first successful resolver', function () {
        $user = createUser();
        $userWorkspace = createWorkspace(['name' => 'User Workspace']);
        $headerWorkspace = createWorkspace(['name' => 'Header Workspace']);

        $userWorkspace->addMember($user, 'owner', setAsCurrent: true);

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => $user);
        $request->headers->set('X-Workspace-Id', (string) $headerWorkspace->id);

        config()->set('workspaces.context.resolvers', [
            AuthUserResolver::class,
            HeaderResolver::class,
        ]);

        $resolver = ChainResolver::fromConfig();
        $resolved = $resolver->resolve($request);

        // Should return user's workspace, not header workspace
        expect($resolved->name)->toBe('User Workspace');
    });

    it('can be created from config', function () {
        config()->set('workspaces.context.resolvers', [
            AuthUserResolver::class,
            HeaderResolver::class,
            SessionResolver::class,
        ]);

        $resolver = ChainResolver::fromConfig();

        expect($resolver)->toBeInstanceOf(ChainResolver::class);
    });
});
