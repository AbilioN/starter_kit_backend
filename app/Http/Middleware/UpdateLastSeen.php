<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UpdateLastSeen
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $user = $request->user();
        if ($user && method_exists($user, 'fill')) {
            // Throttle: only write once per 60 seconds to avoid DB thrash
            if (!$user->last_seen_at || $user->last_seen_at->diffInSeconds(now()) >= 60) {
                $user->updateQuietly(['last_seen_at' => now()]);
            }
        }

        return $response;
    }
}
