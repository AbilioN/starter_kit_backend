<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function () {
            // GodAdmin's own routes - session-guarded, never wrapped by
            // tenant.identify (routes/api.php owns that wrapper entirely).
            Route::middleware('web')->prefix('god')->group(base_path('routes/god.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin.auth'        => \App\Http\Middleware\AdminAuthMiddleware::class,
            'update.last.seen'  => \App\Http\Middleware\UpdateLastSeen::class,
            'tenant.identify'   => \App\Http\Middleware\IdentifyTenant::class,
            'tenant.owner'      => \App\Http\Middleware\RequireTenantOwner::class,
        ]);

        // Laravel's default $middlewarePriority list reorders framework
        // middleware (e.g. Authenticate, from auth:sanctum) to run before any
        // custom middleware that isn't in that list, REGARDLESS of the order
        // routes register them in. Without this, auth:sanctum would run
        // before IdentifyTenant on every tenant-scoped route, resolving
        // Sanctum tokens against whatever connection was configured before
        // the tenant was ever identified.
        //
        // MUST reference the AuthenticatesRequests *interface*, not the
        // Authenticate class - the framework's own default priority list
        // entry is the interface (see Kernel::$middlewarePriority), and
        // addToMiddlewarePriorityRelative() does a strict array_search()
        // against that list. Referencing the concrete class here silently
        // no-ops (falls through to "index not found" -> appended to the END
        // of the priority list instead of before Authenticate), which is
        // exactly backwards from what this is supposed to do. Confirmed via
        // route:list -v showing Authenticate still ahead of IdentifyTenant
        // despite this call being present - see
        // docs/2026-08-04_SANCTUM_TENANT_AUTH_BUG.md for the full writeup.
        $middleware->prependToPriorityList(
            before: \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
            prepend: \App\Http\Middleware\IdentifyTenant::class,
        );

        // Adiciona CORS globalmente
        $middleware->append(\App\Http\Middleware\CorsMiddleware::class);

        // Guards the read-only default of a GodAdmin support session. On the
        // whole api group, not per route, so a route group added later cannot
        // silently end up unguarded — and pinned to run AFTER authentication,
        // since it needs $request->user() to exist.
        $middleware->appendToGroup('api', \App\Http\Middleware\ImpersonationGuard::class);
        $middleware->appendToPriorityList(
            after: \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
            append: \App\Http\Middleware\ImpersonationGuard::class,
        );

        // First in the global stack: every log line written from here on
        // carries a request id, including lines written by middleware that
        // rejects the request before it ever reaches a controller (an
        // unresolved tenant, a suspended one, a failed auth).
        $middleware->prepend(\App\Http\Middleware\AssignRequestContext::class);

        // auth:sanctum requests already short-circuit to a 401 JSON response
        // (expectsJson()) before this is ever consulted - this only affects
        // auth:godadmin's web/Livewire routes, the only guest-redirectable
        // 'web' guard in the app right now.
        $middleware->redirectGuestsTo('/god/login');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
