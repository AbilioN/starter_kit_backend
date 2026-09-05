<?php

namespace App\Jobs;

use App\Application\UseCases\CustomField\ReconcileHostSchemaUseCase;
use App\Jobs\Middleware\EstablishTenantConnection;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

/**
 * Runs the ALTERs an admin's save asked for, off the request.
 *
 * ## Why it is queued at all
 *
 * MySQL commits implicitly on DDL, so the definition write and its ALTER
 * cannot be atomic however the code is arranged. Once that is true, running
 * the ALTER inside the request buys nothing and costs plenty: the panel's
 * ApiClient abandons a request after 10 seconds, and adding an index to a
 * large `appointments` table takes longer than that. So the endpoint answers
 * 202 with `state: pending`, and this closes the loop.
 */
class ReconcileTenantFieldSchema implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * One attempt.
     *
     * A reconcile is idempotent, so retrying is safe in principle — but a
     * retried ALTER against a table that is still rebuilding is a second
     * metadata lock on the busiest table the tenant has. Expected refusals
     * (a ceiling, an unsupported change) never reach here anyway: they close
     * the ledger and mark the definition `failed`, because
     * config/health.php sets the failed_jobs threshold to zero and one
     * tenant's misconfiguration must not page everyone forever.
     */
    public int $tries = 1;

    /** Well above the longest ADD INDEX we expect; the lock wait is 10s. */
    public int $timeout = 600;

    public function __construct(
        private ?string $tenantId,
        private string $hostKey,
        private string $trigger,
        /**
         * Captured at DISPATCH, never read inside middleware().
         *
         * Laravel builds the middleware pipe list BEFORE any of it runs, so
         * EstablishTenantConnection has not executed yet at that point and
         * `DB::connection()->getDatabaseName()` would still be whatever the
         * previous job on this long-lived worker left behind. Two tenants
         * would then share one overlap lock, or one tenant would take two.
         */
        private string $lockKey,
        private ?string $actorAdminId = null,
    ) {
        // Its own queue, and it MUST be listed in config/horizon.php's
        // supervisor. A job dispatched to a queue no supervisor consumes is
        // never processed and never fails — the definition simply sits on
        // `pending` for ever while the health check reports a degradation
        // with no cause. Separate from `default` because these run longer
        // than the 60-second timeout the shared supervisor uses.
        //
        // Set here rather than as a typed property: Queueable already
        // declares $queue as ?string, and redeclaring it with a narrower type
        // is a fatal composition error.
        $this->onQueue('schema');
    }

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [
            new EstablishTenantConnection($this->tenantId),

            // One reconcile per tenant per host at a time. expireAfter is not
            // optional: without it the first reconcile killed mid-run wedges
            // that tenant's host permanently, with no way back but a Redis
            // key nobody knows the name of.
            (new WithoutOverlapping($this->lockKey))
                ->expireAfter(600)
                ->releaseAfter(15),
        ];
    }

    public function handle(ReconcileHostSchemaUseCase $reconcile): void
    {
        $reconcile->execute($this->hostKey, $this->trigger, $this->actorAdminId);
    }
}
