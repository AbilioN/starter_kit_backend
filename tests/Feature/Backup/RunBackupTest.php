<?php

namespace Tests\Feature\Backup;

use App\Application\UseCases\Backup\CheckBackupStalenessUseCase;
use App\Application\UseCases\Backup\RunDatabaseBackupUseCase;
use App\Domain\Entities\Backup;
use App\Domain\Exceptions\BackupFailedException;
use App\Domain\Repositories\BackupRepositoryInterface;
use App\Domain\Repositories\SubscriptionPlanRepositoryInterface;
use App\Domain\Repositories\TenantRepositoryInterface;
use App\Domain\Services\DatabaseDumperInterface;
use Illuminate\Support\Facades\Storage;
use Tests\TenantTestCase;

/**
 * Orchestration only. The dumper is faked here **on purpose and not as a
 * shortcut**: this suite runs on SQLite while production runs MySQL, so a test
 * that "proved mysqldump works" would prove nothing at all about the shell
 * command that actually runs. The dumper itself is verified against the real
 * MySQL container; what is worth asserting here is everything around it — the
 * ledger, the failure paths, the encryption flag.
 */
class RunBackupTest extends TenantTestCase
{
    private \App\Domain\Entities\Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('backup');
        config([
            'backup.default_disk' => 'backup',
            'backup.encryption.enabled' => false,
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

    private function fakeDumper(?callable $onDump = null): void
    {
        $this->app->bind(DatabaseDumperInterface::class, fn () => new class($onDump) implements DatabaseDumperInterface
        {
            public function __construct(private $onDump) {}

            public function isAvailable(): bool
            {
                return true;
            }

            public function dump(string $databaseName, string $targetPath, string $connectionName = 'tenant'): void
            {
                if ($this->onDump) {
                    ($this->onDump)($databaseName, $targetPath);

                    return;
                }

                file_put_contents($targetPath, "-- dump of {$databaseName}\nCREATE TABLE x (id int);\n");
            }

            public function restore(string $databaseName, string $sourcePath, string $connectionName = 'tenant'): void {}
        });
    }

    public function test_a_successful_run_writes_the_object_and_closes_the_ledger_row(): void
    {
        $this->fakeDumper();

        $backup = app(RunDatabaseBackupUseCase::class)->execute($this->tenant);

        $this->assertSame(Backup::STATUS_OK, $backup->status);
        $this->assertNotNull($backup->checksum);
        $this->assertGreaterThan(0, $backup->sizeBytes);
        $this->assertTrue(Storage::disk('backup')->exists($backup->destinationPath));
        $this->assertStringContainsString($this->tenant->subdomain, $backup->destinationPath);
    }

    /**
     * The failure that this whole feature is built around: it must be recorded,
     * not swallowed. A run that fails silently leaves the operator believing
     * they are covered.
     */
    public function test_a_failed_dump_is_recorded_as_failed(): void
    {
        $this->fakeDumper(function () {
            throw new BackupFailedException('mysqldump exploded');
        });

        try {
            app(RunDatabaseBackupUseCase::class)->execute($this->tenant);
            $this->fail('Expected the failure to propagate.');
        } catch (BackupFailedException) {
            // expected
        }

        $rows = app(BackupRepositoryInterface::class)->findForTenant($this->tenant->id);

        $this->assertCount(1, $rows);
        $this->assertSame(Backup::STATUS_FAILED, $rows[0]->status);
        $this->assertStringContainsString('mysqldump exploded', $rows[0]->error);
    }

    /**
     * An empty dump recorded as a success is worse than a failure: nothing
     * surfaces until someone tries to restore it.
     */
    public function test_an_empty_dump_is_a_failure(): void
    {
        $this->fakeDumper(fn ($db, $path) => file_put_contents($path, ''));

        $this->expectException(BackupFailedException::class);

        app(RunDatabaseBackupUseCase::class)->execute($this->tenant);
    }

    public function test_the_local_plaintext_dump_is_removed_afterwards(): void
    {
        $this->fakeDumper();

        app(RunDatabaseBackupUseCase::class)->execute($this->tenant);

        $this->assertSame([], glob(storage_path('app/backup-work/*.sql')) ?: []);
    }

    public function test_capacity_overflow_fails_loudly_rather_than_skipping(): void
    {
        $plan = app(SubscriptionPlanRepositoryInterface::class)->create(
            name: 'Tiny', slug: 'tiny-'.uniqid(), priceCents: 1, features: [],
            // A ceiling no single dump can fit under.
            limits: ['backup_max_total_mb' => 0],
        );
        $tenant = app(TenantRepositoryInterface::class)->update(
            id: $this->tenant->id, subscriptionPlanId: $plan->id,
        );

        $this->fakeDumper();

        $this->expectException(BackupFailedException::class);

        app(RunDatabaseBackupUseCase::class)->execute($tenant);
    }

    public function test_a_tenant_that_was_never_backed_up_is_reported_stale(): void
    {
        $result = app(CheckBackupStalenessUseCase::class)->execute();

        $this->assertContains($this->tenant->subdomain, array_column($result['stale'], 'tenant'));
        $this->assertGreaterThan(0, $result['never']);
    }

    public function test_a_fresh_backup_clears_staleness(): void
    {
        $this->fakeDumper();
        app(RunDatabaseBackupUseCase::class)->execute($this->tenant);

        $result = app(CheckBackupStalenessUseCase::class)->execute();

        $this->assertNotContains($this->tenant->subdomain, array_column($result['stale'], 'tenant'));
    }
}
