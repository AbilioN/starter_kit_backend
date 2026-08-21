<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces the read-only default of a GodAdmin support session, and stamps
 * every log line it produces with the operator behind it.
 *
 * Registered on the whole `api` middleware group rather than route by route,
 * and pinned to run *after* authentication in the priority list (see
 * bootstrap/app.php). That ordering is the entire trick: the guard needs
 * `$request->user()`, which only exists once auth:sanctum has run.
 *
 * Group-wide is a deliberate security choice. Listing it per route group would
 * mean a route group added later silently gets no guard — and "silently gets
 * no guard" here means a read-only support session can write to a customer's
 * data. Failing safe by default is worth more than the explicitness of listing
 * it six times.
 *
 * A request with no token, or with an ordinary admin token, passes straight
 * through.
 */
class ImpersonationGuard
{
    /**
     * Methods that cannot change state, so they are always allowed. HEAD and
     * OPTIONS are here because a browser issues them on its own.
     */
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    /**
     * POSTs that change nothing, and must keep working inside a read-only
     * session.
     *
     *  - Ending the session is itself a POST; without this an operator would
     *    be trapped inside it until it expired.
     *  - `broadcasting/auth` authorizes a channel subscription. Blocking it
     *    silently kills every realtime feature — and "the chat does not
     *    update" is one of the most common things support is asked to
     *    reproduce here, so a session that cannot subscribe cannot do the job
     *    it exists for.
     */
    private function isExempt(Request $request): bool
    {
        return $request->routeIs('admin.impersonation.stop')
            || $request->is('api/broadcasting/auth');
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // The null check comes first and is load-bearing: this guard runs on
        // the whole api group, which includes every unauthenticated route
        // (login, signup, password reset, the health probes).
        $token = $user && method_exists($user, 'currentAccessToken')
            ? $user->currentAccessToken()
            : null;

        // `impersonated_by` — not an ability — is what identifies a support
        // session: ordinary admin tokens are created with ['*'], for which
        // every ability check answers true.
        if (! $token || ! $token->impersonated_by) {
            return $next($request);
        }

        Log::shareContext(['impersonated_by' => (string) $token->impersonated_by]);

        if ($this->isExempt($request)) {
            return $next($request);
        }

        if (! in_array($request->method(), self::SAFE_METHODS, true) && ! $token->can('impersonation:write')) {
            return response()->json([
                'message' => 'This support session is read-only.',
                // Machine-readable so the panel can explain the refusal
                // instead of showing a generic permission error, which would
                // send the operator hunting for a missing role.
                'error' => 'impersonation_read_only',
            ], 403);
        }

        return $next($request);
    }
}
