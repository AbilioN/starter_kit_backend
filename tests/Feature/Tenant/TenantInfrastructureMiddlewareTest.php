<?php

namespace Tests\Feature\Tenant;

use App\Domain\Repositories\InfrastructureProviderRepositoryInterface;
use App\Domain\Repositories\TenantRepositoryInterface;
use App\Jobs\Middleware\EstablishTenantConnection;
use Tests\TenantTestCase;

/**
 * Proves IdentifyTenant/EstablishTenantConnection actually mutate the live
 * broadcasting/filesystem config for real requests and jobs — not just that
 * TenantInfrastructureResolver computes the right array in isolation (see
 * TenantInfrastructureResolverTest for that).
 */
class TenantInfrastructureMiddlewareTest extends TenantTestCase
{
    public function test_a_real_request_applies_the_tenants_storage_provider(): void
    {
        $this->artisan('tenant:provision', [
            'name' => 'Acme Inc',
            'subdomain' => 'acme',
            '--admin-email' => 'owner@acme.test',
            '--admin-password' => 'super-secret',
        ])->assertExitCode(0);

        $tenant = \App\Models\Tenant::on('landlord')->where('subdomain', 'acme')->first();
        $providerId = app(InfrastructureProviderRepositoryInterface::class)->create(
            type: 'storage',
            name: 'Acme Dedicated S3',
            config: ['key' => 'k', 'secret' => 's', 'region' => 'us-east-1', 'bucket' => 'acme-only-bucket'],
        )->id;
        app(TenantRepositoryInterface::class)->update(id: $tenant->id, storageProviderId: $providerId);

        // Whatever the default s3 config was before this request is
        // irrelevant — the assertion is specifically that it now reflects
        // Acme's own bucket after IdentifyTenant runs.
        $this->useTenantHost('acme');
        $this->getJson('/api/tenant/theme')->assertStatus(200);

        $this->assertSame('acme-only-bucket', config('filesystems.disks.s3.bucket'));
    }

    public function test_a_real_request_applies_the_tenants_broadcasting_provider(): void
    {
        $this->artisan('tenant:provision', [
            'name' => 'Acme Inc',
            'subdomain' => 'acme',
            '--admin-email' => 'owner@acme.test',
            '--admin-password' => 'super-secret',
        ])->assertExitCode(0);

        $tenant = \App\Models\Tenant::on('landlord')->where('subdomain', 'acme')->first();
        $providerId = app(InfrastructureProviderRepositoryInterface::class)->create(
            type: 'broadcasting',
            name: 'Acme Dedicated Pusher',
            config: ['key' => 'acme-pusher-key', 'secret' => 's', 'app_id' => 'a', 'cluster' => 'us2'],
        )->id;
        app(TenantRepositoryInterface::class)->update(id: $tenant->id, broadcastingProviderId: $providerId);

        $this->useTenantHost('acme');
        $this->getJson('/api/tenant/theme')
            ->assertStatus(200)
            // Public response includes the resolved key/cluster so the
            // frontend Echo client can connect to the right Pusher app —
            // never the secret, this endpoint has no auth.
            ->assertJsonPath('data.pusher_key', 'acme-pusher-key')
            ->assertJsonPath('data.pusher_cluster', 'us2')
            ->assertJsonMissingPath('data.pusher_secret');

        $this->assertSame('acme-pusher-key', config('broadcasting.connections.pusher.key'));
        $this->assertSame('us2', config('broadcasting.connections.pusher.options.cluster'));
    }

    public function test_a_tenant_with_no_provider_assigned_leaves_config_untouched(): void
    {
        $defaultBucket = config('filesystems.disks.s3.bucket');

        $this->artisan('tenant:provision', [
            'name' => 'Beta Co',
            'subdomain' => 'beta',
            '--admin-email' => 'owner@beta.test',
            '--admin-password' => 'super-secret',
        ])->assertExitCode(0);

        $this->useTenantHost('beta');
        $this->getJson('/api/tenant/theme')
            ->assertStatus(200)
            ->assertJsonPath('data.pusher_key', null)
            ->assertJsonPath('data.pusher_cluster', null);

        $this->assertSame($defaultBucket, config('filesystems.disks.s3.bucket'));
    }

    public function test_establish_tenant_connection_job_middleware_applies_infrastructure_config_too(): void
    {
        $this->artisan('tenant:provision', [
            'name' => 'Acme Inc',
            'subdomain' => 'acme',
            '--admin-email' => 'owner@acme.test',
            '--admin-password' => 'super-secret',
        ])->assertExitCode(0);

        $tenant = \App\Models\Tenant::on('landlord')->where('subdomain', 'acme')->first();
        $providerId = app(InfrastructureProviderRepositoryInterface::class)->create(
            type: 'storage',
            name: 'Acme Dedicated S3',
            config: ['key' => 'k', 'secret' => 's', 'region' => 'us-east-1', 'bucket' => 'acme-job-bucket'],
        )->id;
        app(TenantRepositoryInterface::class)->update(id: $tenant->id, storageProviderId: $providerId);

        // No HTTP request involved at all — proves this doesn't depend on
        // IdentifyTenant or app('currentTenant') being bound.
        (new EstablishTenantConnection($tenant->id))->handle(null, function () {});

        $this->assertSame('acme-job-bucket', config('filesystems.disks.s3.bucket'));
    }
}
