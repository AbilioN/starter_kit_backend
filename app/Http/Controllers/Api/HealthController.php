<?php

namespace App\Http\Controllers\Api;

use App\Application\UseCases\System\CheckSystemHealthUseCase;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Two probes, deliberately separate.
 *
 * `live()` answers "is this process running" and touches nothing. An
 * orchestrator restarts a container that fails liveness, so wiring a database
 * check into it means a brief MySQL hiccup takes every API container down and
 * keeps them down — the classic way a health check causes the outage it was
 * added to detect.
 *
 * `ready()` answers "should this instance receive traffic, and is the work it
 * queues still moving". It is the one a human or a dashboard reads, and it
 * never returns 503 for anything short of a genuinely unusable instance.
 *
 * Both live outside `tenant.identify`: a probe carries no subdomain and no
 * `?tenant=`, and must not 404 for that reason. Note Laravel's own `/up`
 * route (bootstrap/app.php) also exists and is equivalent to `live()` — this
 * one is here so both probes sit on the same `/api` surface the clients and
 * nginx already use.
 */
class HealthController extends Controller
{
    public function live(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'time' => now()->toISOString(),
        ]);
    }

    public function ready(CheckSystemHealthUseCase $checkSystemHealth): JsonResponse
    {
        $result = $checkSystemHealth->execute();

        // `degraded` deliberately still answers 200: work is delayed, not
        // impossible, and pulling the instance out of rotation for a slow AI
        // queue would make things worse rather than better.
        $httpStatus = $result['status'] === 'down' ? 503 : 200;

        return response()->json($result + ['time' => now()->toISOString()], $httpStatus);
    }
}
