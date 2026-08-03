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
        ]);

        // Laravel's default $middlewarePriority list reorders framework
        // middleware (e.g. Authenticate, from auth:sanctum) to run before any
        // custom middleware that isn't in that list, REGARDLESS of the order
        // routes register them in. Without this, auth:sanctum would run
        // before IdentifyTenant on every tenant-scoped route, resolving
        // Sanctum tokens against whatever connection was configured before
        // the tenant was ever identified.
        $middleware->prependToPriorityList(
            before: \Illuminate\Auth\Middleware\Authenticate::class,
            prepend: \App\Http\Middleware\IdentifyTenant::class,
        );

        // Adiciona CORS globalmente
        $middleware->append(\App\Http\Middleware\CorsMiddleware::class);

        // auth:sanctum requests already short-circuit to a 401 JSON response
        // (expectsJson()) before this is ever consulted - this only affects
        // auth:godadmin's web/Livewire routes, the only guest-redirectable
        // 'web' guard in the app right now.
        $middleware->redirectGuestsTo('/god/login');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
