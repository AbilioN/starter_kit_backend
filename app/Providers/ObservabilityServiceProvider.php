<?php

namespace App\Providers;

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;

/**
 * Carries the originating request's id from the HTTP process into the queue
 * worker, so a chat message and the jobs it spawned share one identifier in
 * the logs.
 *
 * This matters more here than in a typical Laravel app: the chat and the whole
 * AI pipeline run in queued jobs, so the interesting half of any incident is
 * written by Horizon, in a different process from the request that started it.
 * Without this the two halves cannot be joined.
 *
 * Done with queue payload hooks rather than a trait on each job so it applies
 * uniformly and no future job can forget to opt in.
 */
class ObservabilityServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Runs in the dispatching process (HTTP request or console command),
        // where the request id is still in the shared log context.
        Queue::createPayloadUsing(function ($connection, $queue, $payload) {
            $requestId = Log::sharedContext()['request_id'] ?? null;

            return $requestId ? ['request_id' => $requestId] : [];
        });

        // Runs in the worker. The context is flushed first: a Horizon worker
        // is a long-lived process handling one job after another, and shared
        // context left over from the previous job would silently mislabel
        // every line of the next one.
        Queue::before(function (JobProcessing $event) {
            Log::flushSharedContext();

            $payload = $event->job->payload();

            Log::shareContext(array_filter([
                'request_id' => $payload['request_id'] ?? null,
                'job' => $payload['displayName'] ?? null,
                'job_id' => $event->job->getJobId() ?: null,
            ]));
        });

        Queue::after(fn (JobProcessed $event) => Log::flushSharedContext());
        Queue::failing(fn (JobFailed $event) => Log::flushSharedContext());
    }
}
