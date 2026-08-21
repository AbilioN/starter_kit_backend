<?php

namespace Tests\Feature\Backup;

use App\Application\UseCases\Backup\PruneBackupsUseCase;
use App\Domain\Entities\Backup;
use App\Domain\Repositories\BackupRepositoryInterface;
use App\Domain\Repositories\SubscriptionPlanRepositoryInterface;
use App\Domain\Repositories\TenantRepositoryInterface;
use App\Models\Backup as BackupModel;
use Illuminate\Support\Facades\Storage;
use Tests\TenantTestCase;

/**
 * Pruning is the only part of this feature that deletes anything, so these are
 * the tests that stand between a retention policy and data loss.
 */
class BackupRetentionTest extends TenantTestCase
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
            limits: ['backup_retention_days' => 7, 'backup_max_total_mb' => 10],
        );

        $this->tenant = app(TenantRepositoryInterface::class)->create(
            name: 'Acme', subdomain: 'acme-'.uniqid(), databaseName: 'db_'.uniqid(),
            subscriptionPlanId: $plan->id, createdVia: 'godadmin',
        );
    }

    private function storedBackup(int $daysAgo, int $sizeMb = 1): string
    {
        $path = 'backups/'.uniqid().'.sql.gz';
        Storage::disk('backup')->put($path, 'dump');

        return BackupModel::create([
            'tenant_id' => $this->tenant->id,
            'kind' => Backup::KIND_DATABASE,
            'status' => Backup::STATUS_OK,
            'disk_name' => 'backup',
            'destination_path' => $path,
            'size_bytes' => $sizeMb * 1024 * 1024,
            'is_encrypted' => false,
            'started_at' => now()->subDays($daysAgo),
            'finished_at' => now()->subDays($daysAgo),
        ])->id;
    }

    public function test_it_deletes_backups_past_the_retention_window(): void
    {
        $old = $this->storedBackup(daysAgo: 30);
        $recent = $this->storedBackup(daysAgo: 1);

        $result = app(PruneBackupsUseCase::class)->execute($this->tenant);

        $this->assertSame(1, $result['pruned']);
        $this->assertSame(Backup::STATUS_PRUNED, BackupModel::find($old)->status);
        $this->assertSame(Backup::STATUS_OK, BackupModel::find($recent)->status);
    }

    /**
     * The rule that must never be "simplified away": a retention window shorter
     * than the backup interval would otherwise leave a tenant with nothing at
     * all, which is the exact state this feature exists to prevent.
     */
    public function test_it_never_deletes_the_last_surviving_backup(): void
    {
        $only = $this->storedBackup(daysAgo: 365);

        $result = app(PruneBackupsUseCase::class)->execute($this->tenant);

        $this->assertSame(0, $result['pruned']);
        $this->assertSame(Backup::STATUS_OK, BackupModel::find($only)->status);
    }

    public function test_capacity_pressure_prunes_oldest_first(): void
    {
        $oldest = $this->storedBackup(daysAgo: 3, sizeMb: 4);
        $middle = $this->storedBackup(daysAgo: 2, sizeMb: 4);
        $newest = $this->storedBackup(daysAgo: 1, sizeMb: 4);

        // 12 MB stored against a 10 MB plan, with 4 MB more on the way.
        app(PruneBackupsUseCase::class)->execute($this->tenant, headroomBytes: 4 * 1024 * 1024);

        $this->assertSame(Backup::STATUS_PRUNED, BackupModel::find($oldest)->status);
        $this->assertSame(Backup::STATUS_PRUNED, BackupModel::find($middle)->status);
        $this->assertSame(Backup::STATUS_OK, BackupModel::find($newest)->status);
    }

    public function test_the_stored_object_is_deleted_not_just_the_ledger_row(): void
    {
        $old = $this->storedBackup(daysAgo: 30);
        $this->storedBackup(daysAgo: 1);

        $path = BackupModel::find($old)->destination_path;
        $this->assertTrue(Storage::disk('backup')->exists($path));

        app(PruneBackupsUseCase::class)->execute($this->tenant);

        $this->assertFalse(Storage::disk('backup')->exists($path));
    }

    public function test_capacity_accounting_ignores_pruned_rows(): void
    {
        $this->storedBackup(daysAgo: 30, sizeMb: 5);
        $this->storedBackup(daysAgo: 1, sizeMb: 3);

        app(PruneBackupsUseCase::class)->execute($this->tenant);

        $this->assertSame(
            3 * 1024 * 1024,
            app(BackupRepositoryInterface::class)->totalStoredBytes($this->tenant->id),
        );
    }
}
