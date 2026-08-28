<?php

namespace Tests\Feature\Backup;

use App\Application\UseCases\Backup\FailStuckBackupRunsUseCase;
use App\Application\UseCases\Backup\RunDatabaseBackupUseCase;
use App\Application\UseCases\System\CheckSystemHealthUseCase;
use App\Domain\Entities\Backup;
use App\Domain\Exceptions\BackupFailedException;
use App\Domain\Repositories\BackupRepositoryInterface;
use App\Domain\Repositories\SubscriptionPlanRepositoryInterface;
use App\Domain\Repositories\TenantRepositoryInterface;
use App\Domain\Services\BackupArchiverInterface;
use App\Domain\Services\DatabaseDumperInterface;
use Illuminate\Support\Facades\Storage;
use Tests\TenantTestCase;

/**
 * The ledger must never lie about a run, and every one of these tests exists
 * because on 2026-08-28 it did.
 *
 * 74 rows sat on `running` — none of them a run in progress, all of them dead
 * for up to a week. Two causes, both covered here: a missing encryption key
 * threw out of `extension()`, two lines above the try/catch that records a
 * failure; and the only thing that ever reconciled abandoned rows was scheduled
 * daily, on a host that is not switched on at 02:30.
 *
 * `running` reads as "in progress" to everything downstream, so the staleness
 * check waits for a report that will never come and alerting has nothing to
 * say. A row that lies is worse than no row.
 */
class BackupLedgerIntegrityTest extends TenantTestCase
{
    private \App\Domain\Entities\Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('backup');
        config([
            'backup.default_disk' => 'backup',
            'filesystems.disks.backup' => ['driver' => 'local', 'root' => storage_path('framework/testing/disks/backup')],
        ]);

        $plan = app(SubscriptionPlanRepositoryInterface::class)->create(
            name: 'Plan', slug: 'plan-'.uniqid(), priceCents: 100, features: [],
            limits: ['backup_frequency_hours' => 24, 'backup_retention_days' => 7],
        );

        $this->tenant = app(TenantRepositoryInterface::class)->create(
            name: 'Acme', subdomain: 'acme-'.uniqid(), databaseName: 'db_'.uniqid(),
            subscriptionPlanId: $plan->id, createdVia: 'godadmin',
        );
    }

    private function fakeDumper(): void
    {
        $this->app->bind(DatabaseDumperInterface::class, fn () => new class implements DatabaseDumperInterface
        {
            public function isAvailable(): bool
            {
                return true;
            }

            public function dump(string $databaseName, string $targetPath, string $connectionName = 'tenant'): void
            {
                file_put_contents($targetPath, "-- dump of {$databaseName}\n");
            }

            public function restore(string $databaseName, string $sourcePath, string $connectionName = 'tenant'): void {}
        });
    }

    private function encryptionEnabledWithoutAKey(): void
    {
        config(['backup.encryption.enabled' => true, 'backup.encryption.key' => null]);
    }

    /**
     * The regression itself. `extension()` names a file; callers reach for it
     * before their own failure handling is in place, so it must not be a place
     * where a misconfiguration surfaces.
     */
    public function test_the_archive_extension_can_be_asked_for_without_a_key(): void
    {
        $this->encryptionEnabledWithoutAKey();

        $this->assertSame('.sql.gz.enc', app(BackupArchiverInterface::class)->extension());
        $this->assertSame('.tar.gz.enc', app(BackupArchiverInterface::class)->extension('.tar'));
    }

    public function test_the_archiver_still_refuses_to_run_without_a_key(): void
    {
        $this->encryptionEnabledWithoutAKey();

        $this->expectException(BackupFailedException::class);

        app(BackupArchiverInterface::class)->assertUsable();
    }

    /**
     * The invariant the whole feature rests on: whatever goes wrong between
     * opening a ledger row and closing it, the row ends up saying so.
     */
    public function test_a_misconfigured_key_leaves_the_row_failed_and_not_running(): void
    {
        $this->fakeDumper();
        $this->encryptionEnabledWithoutAKey();

        try {
            app(RunDatabaseBackupUseCase::class)->execute($this->tenant);
            $this->fail('Expected the misconfiguration to propagate.');
        } catch (BackupFailedException) {
            // expected
        }

        $rows = app(BackupRepositoryInterface::class)->findForTenant($this->tenant->id);

        $this->assertCount(1, $rows);
        $this->assertSame(Backup::STATUS_FAILED, $rows[0]->status);
        $this->assertStringContainsString('BACKUP_ENCRYPTION_KEY', (string) $rows[0]->error);
    }

    /**
     * Refused up front, so a nightly sweep across 200 tenants does not produce
     * 200 identical failures and 200 rows to reconcile.
     */
    public function test_the_command_refuses_before_opening_any_row(): void
    {
        $this->fakeDumper();
        $this->encryptionEnabledWithoutAKey();

        $this->artisan('backup:run')
            ->expectsOutputToContain('BACKUP_ENCRYPTION_KEY')
            ->assertExitCode(1);

        $this->assertSame([], app(BackupRepositoryInterface::class)->findForTenant($this->tenant->id));
    }

    public function test_an_abandoned_run_is_closed_and_a_live_one_is_left_alone(): void
    {
        config(['backup.running_timeout_minutes' => 120]);

        $repository = app(BackupRepositoryInterface::class);

        $abandoned = $repository->startRun($this->tenant->id, Backup::KIND_DATABASE);
        $live = $repository->startRun($this->tenant->id, Backup::KIND_FILES);

        \App\Models\Backup::query()
            ->where('id', $abandoned->id)
            ->update(['started_at' => now()->subHours(6)]);

        $closed = app(FailStuckBackupRunsUseCase::class)->execute();

        $this->assertCount(1, $closed);
        $this->assertSame($abandoned->id, $closed[0]->id);
        $this->assertSame(Backup::STATUS_FAILED, $repository->findById($abandoned->id)?->status);
        $this->assertSame(Backup::STATUS_RUNNING, $repository->findById($live->id)?->status);
    }

    /**
     * Asked of the environment this process actually has, which is the only way
     * a container running a week-old .env can report itself.
     */
    public function test_health_reports_a_process_that_cannot_encrypt_its_backups(): void
    {
        $this->encryptionEnabledWithoutAKey();

        $configuration = app(CheckSystemHealthUseCase::class)->execute()['checks']['configuration'];

        $this->assertSame('degraded', $configuration['status']);
        $this->assertNotEmpty(array_filter(
            $configuration['problems'],
            fn (string $problem) => str_contains($problem, 'BACKUP_ENCRYPTION_KEY'),
        ));
    }
}
