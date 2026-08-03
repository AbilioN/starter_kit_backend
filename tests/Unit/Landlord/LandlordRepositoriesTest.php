<?php

namespace Tests\Unit\Landlord;

use App\Domain\Repositories\GodAdminRepositoryInterface;
use App\Domain\Repositories\LandlordAuditLogRepositoryInterface;
use App\Domain\Repositories\SubscriptionPlanRepositoryInterface;
use App\Domain\Repositories\TenantRepositoryInterface;
use Tests\TenantTestCase;

class LandlordRepositoriesTest extends TenantTestCase
{
    public function test_god_admin_repository_creates_and_finds_by_email(): void
    {
        $repository = app(GodAdminRepositoryInterface::class);

        $created = $repository->create('Root', 'root@starterkit.test', 'secret-password');

        $this->assertNotEmpty($created->id);
        $this->assertTrue($created->validatePassword('secret-password'));

        $found = $repository->findByEmail('root@starterkit.test');

        $this->assertNotNull($found);
        $this->assertSame($created->id, $found->id);
    }

    public function test_subscription_plan_repository_creates_updates_and_filters_active(): void
    {
        $repository = app(SubscriptionPlanRepositoryInterface::class);

        $plan = $repository->create(
            name: 'Starter',
            slug: 'starter',
            priceCents: 4900,
            features: ['chat' => true, 'file_upload' => false],
            limits: ['max_admins' => 3],
            isActive: true,
        );

        $this->assertSame(['chat' => true, 'file_upload' => false], $plan->features);
        $this->assertCount(1, $repository->findActive());

        $updated = $repository->update($plan->id, isActive: false);

        $this->assertFalse($updated->isActive);
        $this->assertCount(0, $repository->findActive());
    }

    public function test_tenant_repository_creates_and_finds_by_subdomain(): void
    {
        $planRepository = app(SubscriptionPlanRepositoryInterface::class);
        $tenantRepository = app(TenantRepositoryInterface::class);

        $plan = $planRepository->create('Starter', 'starter', null, [], []);

        $tenant = $tenantRepository->create(
            name: 'Tenant A',
            subdomain: 'tenant-a',
            databaseName: 'starter_kit_tenant_a',
            subscriptionPlanId: $plan->id,
            createdVia: 'godadmin',
        );

        $this->assertSame('pending', $tenant->status);

        $found = $tenantRepository->findBySubdomain('tenant-a');
        $this->assertNotNull($found);
        $this->assertSame($tenant->id, $found->id);

        $activated = $tenantRepository->update($tenant->id, status: 'active');
        $this->assertTrue($activated->isActive());
    }

    public function test_landlord_audit_log_repository_logs_and_filters_by_action(): void
    {
        $repository = app(LandlordAuditLogRepositoryInterface::class);

        $repository->log(
            actorType: 'godadmin',
            actorId: (string) \Illuminate\Support\Str::uuid(),
            action: 'tenant_created',
            model: 'Tenant',
            modelId: (string) \Illuminate\Support\Str::uuid(),
            metadata: ['subdomain' => 'tenant-a'],
        );

        $result = $repository->findWithFilters(['action' => 'tenant_created']);

        $this->assertCount(1, $result['data']);
        $this->assertSame('tenant_created', $result['data'][0]->action);
        $this->assertSame(['subdomain' => 'tenant-a'], $result['data'][0]->metadata);
    }
}
