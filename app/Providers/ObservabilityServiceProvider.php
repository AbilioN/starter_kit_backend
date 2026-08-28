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

            return array_filter([
                'request_id' => $requestId,
                // Carried for the same reason and by the same route as the
                // request id, but for a different consumer: a notification
                // e-mail is RENDERED by a job, minutes after the request that
                // asked for it is gone. Without this the queue always renders
                // in the app default, so a tenant running in Portuguese sends
                // English mail no matter what anyone chose (roadmap 5.8).
                'locale' => app()->getLocale(),
            ]);
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

            // Restored per job, and reset below with the context: a worker is a
            // long-lived process, so a locale left over from the previous job
            // would silently translate the next one.
            app()->setLocale($payload['locale'] ?? config('app.locale'));
        });

        Queue::after(function (JobProcessed $event): void {
            Log::flushSharedContext();
            app()->setLocale(config('app.locale'));
        });

        Queue::failing(function (JobFailed $event): void {
            Log::flushSharedContext();
            app()->setLocale(config('app.locale'));
        });

        $this->tagErrorReportsWithRequestContext();
    }

    /**
     * Puts the same request_id and tenant_id on the error report that the logs
     * already carry (roadmap 5.1.B).
     *
     * Done as an event processor rather than by setting tags at the point the
     * context is created: the processor reads the shared context at *send*
     * time, so it works identically in an HTTP request and inside a Horizon
     * worker, and no future entry point can forget to opt in — the same reason
     * the queue hooks above are payload hooks and not a trait.
     *
     * **With no DSN configured this is dead code and nothing leaves this
     * infrastructure.** That is deliberate: whether stack traces from a
     * multi-tenant BtoB product may be sent to a third party is a decision for
     * whoever owns the data, not a consequence of installing a package. The
     * SDK speaks one protocol and both candidate destinations accept it —
     * Sentry's own service and a self-hosted GlitchTip — so the choice stays a
     * DSN, and stays reversible.
     */
    private function tagErrorReportsWithRequestContext(): void
    {
        if (blank(config('sentry.dsn')) || ! class_exists(\Sentry\State\Scope::class)) {
            return;
        }

        $scrubber = new \App\Infrastructure\Services\ErrorReportScrubber;

        \Sentry\configureScope(function (\Sentry\State\Scope $scope) use ($scrubber): void {
            $scope->addEventProcessor(function (\Sentry\Event $event) use ($scrubber): \Sentry\Event {
                foreach (Log::sharedContext() as $key => $value) {
                    if (is_scalar($value)) {
                        $event->setTag($key, (string) $value);
                    }
                }

                // Last thing before the event leaves: see ErrorReportScrubber
                // for what and why. send_default_pii=false does not cover it.
                return $scrubber->scrub($event);
            });
        });
    }
}
