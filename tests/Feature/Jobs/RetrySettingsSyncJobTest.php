<?php

namespace Tests\Feature\Jobs;

use App\Domain\Repositories\SubscriptionPlanRepositoryInterface;
use App\Jobs\RetrySettingsSyncJob;
use App\Models\Setting;
use App\Models\Tenant;
use Tests\TenantTestCase;

class RetrySettingsSyncJobTest extends TenantTestCase
{
    public function test_job_re_establishes_tenant_connection_and_syncs_features(): void
    {
        $tenant = $this->actingAsTenant();
        $plan = app(SubscriptionPlanRepositoryInterface::class)->create(
            name: 'Pro',
            slug: 'pro',
            priceCents: 9900,
            features: ['chat' => true],
            limits: [],
        );

        // Simulate the "wrong database.default" scenario a real queue
        // worker would start in, same as TenantAwareJobTest.
        $tenantId = Tenant::on('landlord')->where('subdomain', 'testing')->first()->id;
        config(['database.default' => 'sqlite']);

        RetrySettingsSyncJob::dispatch($tenantId, $plan->id);

        config(['database.default' => 'tenant']);
        $chatSetting = Setting::where('key', 'features.chat')->first();
        $this->assertNotNull($chatSetting);
        $this->assertSame('1', $chatSetting->value);
    }
}
