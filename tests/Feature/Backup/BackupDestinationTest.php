<?php

namespace Tests\Feature\Backup;

use App\Domain\Exceptions\BackupDestinationException;
use App\Domain\Repositories\InfrastructureProviderRepositoryInterface;
use App\Domain\Repositories\SubscriptionPlanRepositoryInterface;
use App\Domain\Repositories\TenantRepositoryInterface;
use App\Domain\Services\TenantInfrastructureResolverInterface;
use Tests\TenantTestCase;

/**
 * The resolution chain, and the one rule that separates `backup` from the three
 * infrastructure types it otherwise copies: nothing resolved must never mean
 * "quietly skip".
 */
class BackupDestinationTest extends TenantTestCase
{
    private function provider(string $name, array $config = []): string
    {
        return app(InfrastructureProviderRepositoryInterface::class)->create(
            type: 'backup',
            name: $name,
            config: array_merge([
                'driver' => 's3',
                'key' => 'k',
                'secret' => 's',
                'bucket' => 'bucket-'.$name,
                'path_prefix' => 'backups',
            ], $config),
        )->id;
    }

    private function tenant(?string $planProviderId = null, ?string $tenantProviderId = null): \App\Domain\Entities\Tenant
    {
        $plan = app(SubscriptionPlanRepositoryInterface::class)->create(
            name: 'Plan', slug: 'plan-'.uniqid(), priceCents: 100, features: [], limits: [],
            backupProviderId: $planProviderId,
        );

        $tenant = app(TenantRepositoryInterface::class)->create(
            name: 'Acme', subdomain: 'acme-'.uniqid(), databaseName: 'db_'.uniqid(),
            subscriptionPlanId: $plan->id, createdVia: 'godadmin',
        );

        return $tenantProviderId
            ? app(TenantRepositoryInterface::class)->update(id: $tenant->id, backupProviderId: $tenantProviderId)
            : $tenant;
    }

    public function test_tenant_override_wins_over_the_plan_default(): void
    {
        $tenant = $this->tenant(
            planProviderId: $this->provider('plan-default'),
            tenantProviderId: $this->provider('tenant-override'),
        );

        $resolved = app(TenantInfrastructureResolverInterface::class)->resolveBackupConfig($tenant);

        $this->assertSame('bucket-tenant-override', $resolved['config']['bucket']);
    }

    public function test_plan_default_applies_when_the_tenant_has_no_override(): void
    {
        $tenant = $this->tenant(planProviderId: $this->provider('plan-default'));

        $resolved = app(TenantInfrastructureResolverInterface::class)->resolveBackupConfig($tenant);

        $this->assertSame('bucket-plan-default', $resolved['config']['bucket']);
    }

    public function test_it_falls_back_to_the_global_backup_disk(): void
    {
        config(['backup.default_disk' => 'backup', 'filesystems.disks.backup' => [
            'driver' => 'local', 'root' => '/tmp/backup-fallback',
        ]]);

        $resolved = app(TenantInfrastructureResolverInterface::class)->resolveBackupConfig($this->tenant());

        $this->assertSame('backup', $resolved['disk_name']);
        $this->assertNull($resolved['provider_id']);
    }

    /**
     * The whole point. Every other resolver in this class returns null here and
     * the caller shrugs; this one must not.
     */
    public function test_it_throws_when_nothing_at_all_is_configured(): void
    {
        config(['backup.default_disk' => 'nonexistent-disk']);

        $this->expectException(BackupDestinationException::class);

        app(TenantInfrastructureResolverInterface::class)->resolveBackupConfig($this->tenant());
    }

    /**
     * Half-configured is worse than unconfigured: it looks assigned in the
     * panel and writes nowhere.
     */
    public function test_it_throws_when_a_provider_has_no_bucket(): void
    {
        $tenant = $this->tenant(planProviderId: $this->provider('broken', ['bucket' => null]));

        $this->expectException(BackupDestinationException::class);

        app(TenantInfrastructureResolverInterface::class)->resolveBackupConfig($tenant);
    }

    /**
     * A provider switched off must stop receiving NEW backups but must never
     * cost the ability to read the ones already written to it.
     */
    public function test_an_inactive_provider_is_still_readable_by_id(): void
    {
        $providerId = $this->provider('retired');
        app(InfrastructureProviderRepositoryInterface::class)->update(id: $providerId, isActive: false);

        $resolved = app(TenantInfrastructureResolverInterface::class)->resolveBackupConfigById($providerId);

        $this->assertSame('bucket-retired', $resolved['config']['bucket']);
    }
}
