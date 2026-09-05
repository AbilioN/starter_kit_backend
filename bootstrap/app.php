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
            'agent.worker'      => \App\Http\Middleware\AgentWorkerAuth::class,
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

        // Which language the API answers in (roadmap 5.8). Group-wide and
        // pinned after authentication for the same two reasons as the guard
        // above: it needs $request->user() to read that person's own choice,
        // and a route group added later must not silently lose it.
        $middleware->appendToGroup('api', \App\Http\Middleware\SetLocale::class);
        $middleware->appendToPriorityList(
            after: \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
            append: \App\Http\Middleware\SetLocale::class,
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
        // Until 2026-09-04 this body was empty, which meant a domain
        // exception that no controller happened to catch became a 500.
        // Three controllers were fixed one at a time by adding the catch
        // (SettingController, FileController, AuditController — see
        // docs/03-multitenancy-plan.md), and the two newest ones,
        // AgendaController and AppointmentController, call
        // AuthorizeActionUseCase with no catch at all: a permission denial
        // on the agenda answered 500 in production. Fixing it per method
        // means every future controller has to remember; fixing it here
        // means the default is right and the per-method catches that
        // already exist keep working untouched, since they run first.
        //
        // Gated on the request wanting JSON: these renderers are global,
        // and routes/god.php runs in the `web` group where a Livewire
        // screen must keep its HTML error page.
        $jsonOnly = fn (\Illuminate\Http\Request $request): bool => $request->expectsJson() || $request->is('api/*');

        // The newer envelope — {success:false, message} — matching Setting,
        // File, Audit and Template. The older {error: …} shape in the RBAC
        // controllers stays as-is; those methods catch before reaching here.
        $envelope = fn (string $message, int $status) => response()->json(
            ['success' => false, 'message' => $message],
            $status,
        );

        $exceptions->render(function (\App\Domain\Exceptions\AuthorizationException $e, $request) use ($jsonOnly, $envelope) {
            return $jsonOnly($request) ? $envelope($e->getMessage(), 403) : null;
        });

        // 402 Payment Required, matching FileController's existing mapping
        // for the same exception — a plan cap is a billing answer, not a
        // permission one.
        $exceptions->render(function (\App\Domain\Exceptions\PlanLimitExceededException $e, $request) use ($jsonOnly, $envelope) {
            return $jsonOnly($request) ? $envelope($e->getMessage(), 402) : null;
        });

        $exceptions->render(function (\App\Domain\Exceptions\CustomFieldConflictException $e, $request) use ($jsonOnly, $envelope) {
            return $jsonOnly($request) ? $envelope($e->getMessage(), 409) : null;
        });

        // A file the extractor could not read, or one with no text in it — a
        // scan, usually. 422 rather than 500: the submission is what is wrong,
        // and the message names the fix.
        $exceptions->render(function (\App\Domain\Exceptions\DocumentExtractionException $e, $request) use ($jsonOnly, $envelope) {
            return $jsonOnly($request) ? $envelope($e->getMessage(), 422) : null;
        });
    })->create();
