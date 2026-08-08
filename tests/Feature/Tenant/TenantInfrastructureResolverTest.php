<?php

namespace Tests\Feature\Tenant;

use App\Domain\Repositories\InfrastructureProviderRepositoryInterface;
use App\Domain\Repositories\SubscriptionPlanRepositoryInterface;
use App\Domain\Repositories\TenantRepositoryInterface;
use App\Domain\Services\TenantInfrastructureResolverInterface;
use Tests\TenantTestCase;

/**
 * Resolution order: tenant's own override wins, then the tenant's plan's
 * default, then null (nothing configured — caller must leave config()
 * untouched, see IdentifyTenant::applyInfrastructureConfig()).
 */
class TenantInfrastructureResolverTest extends TenantTestCase
{
    private function makeProvider(string $type, array $config): string
    {
        return app(InfrastructureProviderRepositoryInterface::class)->create(
            type: $type,
            name: "Test {$type} provider",
            config: $config,
        )->id;
    }

    public function test_resolves_null_when_neither_tenant_nor_plan_has_a_provider(): void
    {
        $tenant = $this->actingAsTenant('acme');
        $entity = app(TenantRepositoryInterface::class)->findById($tenant->id);

        $resolver = app(TenantInfrastructureResolverInterface::class);

        $this->assertNull($resolver->resolveBroadcastingConfig($entity));
        $this->assertNull($resolver->resolveStorageConfig($entity));
    }

    public function test_resolves_the_plans_default_when_the_tenant_has_no_override(): void
    {
        $providerId = $this->makeProvider('broadcasting', [
            'key' => 'plan-key', 'secret' => 'plan-secret', 'app_id' => 'plan-app', 'cluster' => 'eu', 'host' => null,
        ]);
        $plan = app(SubscriptionPlanRepositoryInterface::class)->create(
            name: 'Pro', slug: 'pro', priceCents: null, features: [], limits: [],
            broadcastingProviderId: $providerId,
        );

        $tenant = $this->actingAsTenant('acme');
        app(TenantRepositoryInterface::class)->update(id: $tenant->id, subscriptionPlanId: $plan->id);
        $entity = app(TenantRepositoryInterface::class)->findById($tenant->id);

        $config = app(TenantInfrastructureResolverInterface::class)->resolveBroadcastingConfig($entity);

        $this->assertSame('plan-key', $config['key']);
        $this->assertSame('eu', $config['options']['cluster']);
    }

    public function test_tenant_override_wins_over_the_plans_default(): void
    {
        $planProviderId = $this->makeProvider('broadcasting', ['key' => 'plan-key', 'secret' => 's', 'app_id' => 'a', 'cluster' => 'eu']);
        $tenantProviderId = $this->makeProvider('broadcasting', ['key' => 'tenant-key', 'secret' => 's', 'app_id' => 'a', 'cluster' => 'us2']);

        $plan = app(SubscriptionPlanRepositoryInterface::class)->create(
            name: 'Pro', slug: 'pro', priceCents: null, features: [], limits: [],
            broadcastingProviderId: $planProviderId,
        );

        $tenant = $this->actingAsTenant('acme');
        app(TenantRepositoryInterface::class)->update(
            id: $tenant->id,
            subscriptionPlanId: $plan->id,
            broadcastingProviderId: $tenantProviderId,
        );
        $entity = app(TenantRepositoryInterface::class)->findById($tenant->id);

        $config = app(TenantInfrastructureResolverInterface::class)->resolveBroadcastingConfig($entity);

        $this->assertSame('tenant-key', $config['key']);
    }

    public function test_inactive_provider_is_treated_as_unset(): void
    {
        $providerId = $this->makeProvider('storage', [
            'key' => 'k', 'secret' => 's', 'region' => 'us-east-1', 'bucket' => 'acme-bucket',
        ]);
        app(InfrastructureProviderRepositoryInterface::class)->update(id: $providerId, isActive: false);

        $tenant = $this->actingAsTenant('acme');
        app(TenantRepositoryInterface::class)->update(id: $tenant->id, storageProviderId: $providerId);
        $entity = app(TenantRepositoryInterface::class)->findById($tenant->id);

        $config = app(TenantInfrastructureResolverInterface::class)->resolveStorageConfig($entity);

        $this->assertNull($config);
    }

    public function test_resolves_storage_config_shape_for_the_s3_disk(): void
    {
        $providerId = $this->makeProvider('storage', [
            'key' => 'AKIA...', 'secret' => 'secret', 'region' => 'us-east-1',
            'bucket' => 'acme-bucket', 'endpoint' => 'https://s3.example.com', 'use_path_style' => true,
        ]);

        $tenant = $this->actingAsTenant('acme');
        app(TenantRepositoryInterface::class)->update(id: $tenant->id, storageProviderId: $providerId);
        $entity = app(TenantRepositoryInterface::class)->findById($tenant->id);

        $config = app(TenantInfrastructureResolverInterface::class)->resolveStorageConfig($entity);

        $this->assertSame('s3', $config['driver']);
        $this->assertSame('acme-bucket', $config['bucket']);
        $this->assertTrue($config['use_path_style_endpoint']);
    }
}
