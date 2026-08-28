<?php

namespace App\Http\Middleware;

use App\Domain\AgentTools\Exceptions\AgentToolFailure;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Step 1 of the executor pipeline, and the feature's kill switch.
 *
 * Runs before anything touches Redis, so an unauthenticated caller cannot use
 * the endpoint to probe whether a grant token exists.
 *
 * With no worker key configured the route **404s**, deliberately: an
 * installation that has not opted in should be indistinguishable from one where
 * the feature was never built.
 */
class AgentWorkerAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $configured = (string) config('agent_tools.worker_key');

        if ($configured === '') {
            abort(404);
        }

        $presented = (string) $request->header('X-Agent-Worker-Key', '');

        // Constant-time: a timing-distinguishable compare on a shared secret is
        // worth avoiding even behind an internal network.
        if ($presented === '' || ! hash_equals($configured, $presented)) {
            $failure = AgentToolFailure::workerKeyInvalid();

            return response()->json($failure->toArray(), $failure->status);
        }

        return $next($request);
    }
}
