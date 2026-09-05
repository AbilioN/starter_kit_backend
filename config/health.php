<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Readiness thresholds
    |--------------------------------------------------------------------------
    |
    | What counts as "degraded" for the /api/health/ready probe. These are
    | deliberately config rather than constants: the right numbers depend on
    | the host and on how many tenants share it, and they are the values an
    | alerting rule will eventually fire on (Sprint 5.1.E — the alert
    | destination is still undecided, so `health:check` is the single place
    | to attach one when it is).
    |
    */

    'ai_bus' => [
        // Raw Redis lists shared with starter_kit_ai_microservice. Horizon
        // does not see these — they are not Laravel queues — so this is the
        // only place they are observed at all.
        'request_queue' => 'openai_requests',
        'response_queue' => 'openai_responses',

        // A backlog is normal under load; a *growing* backlog is not. Depth
        // alone cannot tell the two apart, which is why age matters more.
        'max_depth' => (int) env('HEALTH_AI_MAX_DEPTH', 20),
        'max_age_seconds' => (int) env('HEALTH_AI_MAX_AGE_SECONDS', 120),

        // Written by the Python worker when a provider call fails in a way
        // nobody here can retry away — no credits, a bad key — and DELETED by
        // it on the next success. A cross-repo contract, like the heartbeat:
        // renaming it on one side silently blinds the other.
        //
        // This is the only check that can see a billing outage. Everything
        // else about the bus stays perfectly healthy through one: the queues
        // drain, the worker answers, the heartbeat is seconds old — and every
        // reply is an error the user is told to retry.
        // A literal, deliberately, exactly like `heartbeat_key` five lines
        // below. An env() here is overridable on THIS side only — the worker's
        // own name for it is PROVIDER_ERROR_KEY — so renaming it in one .env
        // leaves the check reading a key nobody writes, with no error on
        // either side and the one signal that can see a billing outage
        // permanently dark.
        'provider_error_key' => 'ai_worker:provider_error',

        // Written by the Python worker's heartbeat thread. Absent or stale
        // means the worker is gone — which, with an empty queue, is otherwise
        // indistinguishable from a healthy idle worker.
        'heartbeat_key' => 'ai_worker:heartbeat',
        'heartbeat_max_age_seconds' => (int) env('HEALTH_AI_HEARTBEAT_MAX_AGE_SECONDS', 60),
    ],

    'tenant_databases' => [
        // How old the scheduled check's result may be before the probe stops
        // trusting it. Must stay comfortably above the schedule's interval
        // (every 10 minutes) or a single skipped run reads as an incident.
        'max_age_minutes' => (int) env('HEALTH_TENANT_DB_MAX_AGE_MINUTES', 30),
    ],

    'failed_jobs' => [
        // Counted over a window rather than in total: old failures are
        // history, recent ones are an incident.
        'window_minutes' => (int) env('HEALTH_FAILED_JOBS_WINDOW_MINUTES', 15),
        'max' => (int) env('HEALTH_FAILED_JOBS_MAX', 0),
    ],

];
