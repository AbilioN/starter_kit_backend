<?php

namespace Tests\Feature\CustomFields;

use App\Application\UseCases\CustomField\ReconcileHostSchemaUseCase;
use App\Models\CustomFieldReconcileRun;
use Tests\TenantTestCase;

/**
 * The ledger has to survive the failure, not only the success.
 *
 * `custom_field_reconcile_runs` exists so that a reconcile which died halfway
 * leaves something an operator can read. The backup system learned this the
 * expensive way in the other direction — 74 runs stuck on `running` because
 * one line sat above the try. This is its mirror image, and it happened here
 * on 2026-09-05: a Horizon worker still holding a registry from before `users`
 * was registered threw while resolving the host, which sat ABOVE the ledger
 * write, so two failed attempts left no trace at all and the definitions sat
 * on `pending` with nothing to explain them.
 */
class ReconcileLedgerTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsTenant('cfledger');
    }

    public function test_an_unknown_host_still_leaves_a_closed_ledger_row(): void
    {
        $this->assertSame(0, CustomFieldReconcileRun::count());

        try {
            app(ReconcileHostSchemaUseCase::class)->execute('invoices', CustomFieldReconcileRun::TRIGGER_COMMAND);
            $this->fail('An unknown host must still throw.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('invoices', $e->getMessage());
        }

        $run = CustomFieldReconcileRun::sole();

        // Recorded, closed, and naming what was asked for — not "running", and
        // not absent.
        $this->assertSame('invoices', $run->host);
        $this->assertSame(CustomFieldReconcileRun::STATUS_FAILED, $run->status);
        $this->assertNotNull($run->finished_at);
        $this->assertStringContainsString('invoices', (string) $run->error);
    }

    public function test_a_failure_is_never_left_reading_as_work_in_progress(): void
    {
        // The reconciler is MySQL-only, so under the fast gate assertUsable()
        // is what throws — a different failure point from the one above, and
        // the ledger must close for it too.
        try {
            app(ReconcileHostSchemaUseCase::class)->execute('appointments', CustomFieldReconcileRun::TRIGGER_SAVE);
        } catch (\Throwable) {
            // Expected under SQLite.
        }

        foreach (CustomFieldReconcileRun::all() as $run) {
            $this->assertNotSame(
                CustomFieldReconcileRun::STATUS_RUNNING,
                $run->status,
                'A finished attempt must never still read as running.',
            );
        }
    }
}
