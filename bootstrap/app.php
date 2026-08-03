<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
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
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
