<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gives every request an id and puts it in the log context, so the lines a
 * single request produced can be pulled out of a shared log stream.
 *
 * Runs before IdentifyTenant (see bootstrap/app.php): the tenant is added to
 * the same shared context later, once resolved, so lines written *before*
 * resolution still carry the request id.
 *
 * The id is echoed back as X-Request-Id, which is what makes a user-reported
 * problem findable — "the screenshot shows this id" beats "it was around 14h".
 * An inbound X-Request-Id is honoured so a caller (nginx, another service, the
 * Nuxt panel) can correlate its own trace with ours, but it is validated
 * first: it lands in log lines, and an unvalidated header would let a caller
 * inject newlines or arbitrary length into them.
 */
class AssignRequestContext
{
    private const MAX_LENGTH = 64;

    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $this->resolveRequestId($request);

        Log::shareContext(['request_id' => $requestId]);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }

    private function resolveRequestId(Request $request): string
    {
        $incoming = (string) $request->headers->get('X-Request-Id', '');

        if ($incoming !== ''
            && strlen($incoming) <= self::MAX_LENGTH
            && preg_match('/^[A-Za-z0-9._-]+$/', $incoming) === 1) {
            return $incoming;
        }

        return (string) Str::uuid();
    }
}
