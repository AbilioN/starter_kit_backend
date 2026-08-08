<?php

namespace Tests\Feature\GodAdmin;

use App\Domain\Repositories\InfrastructureProviderRepositoryInterface;
use App\Domain\Repositories\SubscriptionPlanRepositoryInterface;
use App\Domain\Repositories\TenantRepositoryInterface;
use App\Livewire\InfrastructureProviders\Form;
use App\Livewire\InfrastructureProviders\Index;
use App\Livewire\SubscriptionPlans\Form as SubscriptionPlanForm;
use App\Livewire\Tenants\Show;
use App\Models\GodAdmin;
use Livewire\Livewire;
use Tests\TenantTestCase;

class InfrastructureProviderManagementTest extends TenantTestCase
{
    private GodAdmin $godAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsTenant();

        $this->godAdmin = GodAdmin::create([
            'name' => 'Root',
            'email' => 'root@starterkit.test',
            'password' => 'secret-password',
        ]);
    }

    public function test_godadmin_can_create_a_broadcasting_provider(): void
    {
        Livewire::actingAs($this->godAdmin, 'godadmin')
            ->test(Form::class)
            ->set('type', 'broadcasting')
            ->set('name', 'Shared Pusher')
            ->set('configKey', 'k')
            ->set('configSecret', 's')
            ->set('configAppId', 'a')
            ->set('configCluster', 'eu')
            ->call('save')
            ->assertHasNoErrors();

        $providers = app(InfrastructureProviderRepositoryInterface::class)->findByType('broadcasting');
        $this->assertCount(1, $providers);
        $this->assertSame('Shared Pusher', $providers[0]->name);
        $this->assertSame('eu', $providers[0]->config['cluster']);
    }

    public function test_godadmin_can_create_a_storage_provider(): void
    {
        Livewire::actingAs($this->godAdmin, 'godadmin')
            ->test(Form::class)
            ->set('type', 'storage')
            ->set('name', 'Dedicated S3')
            ->set('configKey', 'k')
            ->set('configSecret', 's')
            ->set('configBucket', 'acme-bucket')
            ->call('save')
            ->assertHasNoErrors();

        $providers = app(InfrastructureProviderRepositoryInterface::class)->findByType('storage');
        $this->assertCount(1, $providers);
        $this->assertSame('acme-bucket', $providers[0]->config['bucket']);
    }

    public function test_creating_a_broadcasting_provider_without_an_app_id_fails_validation(): void
    {
        Livewire::actingAs($this->godAdmin, 'godadmin')
            ->test(Form::class)
            ->set('type', 'broadcasting')
            ->set('name', 'Shared Pusher')
            ->set('configKey', 'k')
            ->set('configSecret', 's')
            ->set('configCluster', 'eu')
            ->call('save')
            ->assertHasErrors(['configAppId' => 'required_if']);
    }

    public function test_godadmin_can_edit_a_provider(): void
    {
        $provider = app(InfrastructureProviderRepositoryInterface::class)->create(
            type: 'storage', name: 'Old Name', config: ['key' => 'k', 'secret' => 's', 'bucket' => 'old-bucket'],
        );

        Livewire::actingAs($this->godAdmin, 'godadmin')
            ->test(Form::class, ['providerId' => $provider->id])
            ->assertSet('configBucket', 'old-bucket')
            ->set('name', 'New Name')
            ->set('configBucket', 'new-bucket')
            ->call('save')
            ->assertHasNoErrors();

        $updated = app(InfrastructureProviderRepositoryInterface::class)->findById($provider->id);
        $this->assertSame('New Name', $updated->name);
        $this->assertSame('new-bucket', $updated->config['bucket']);
    }

    public function test_godadmin_can_delete_a_provider(): void
    {
        $provider = app(InfrastructureProviderRepositoryInterface::class)->create(
            type: 'storage', name: 'To Delete', config: ['key' => 'k', 'secret' => 's', 'bucket' => 'b'],
        );

        Livewire::actingAs($this->godAdmin, 'godadmin')
            ->test(Index::class)
            ->call('delete', $provider->id);

        $this->assertNull(app(InfrastructureProviderRepositoryInterface::class)->findById($provider->id));
    }

    public function test_godadmin_can_assign_a_default_provider_to_a_plan(): void
    {
        $provider = app(InfrastructureProviderRepositoryInterface::class)->create(
            type: 'broadcasting', name: 'Shared Pusher', config: ['key' => 'k', 'secret' => 's', 'app_id' => 'a', 'cluster' => 'eu'],
        );
        $plan = app(SubscriptionPlanRepositoryInterface::class)->create(
            name: 'Basic', slug: 'basic', priceCents: null, features: [], limits: [],
        );

        Livewire::actingAs($this->godAdmin, 'godadmin')
            ->test(SubscriptionPlanForm::class, ['planId' => $plan->id])
            ->set('broadcastingProviderId', $provider->id)
            ->call('save')
            ->assertHasNoErrors();

        $updated = app(SubscriptionPlanRepositoryInterface::class)->findById($plan->id);
        $this->assertSame($provider->id, $updated->broadcastingProviderId);
    }

    public function test_godadmin_can_set_and_clear_a_tenant_infrastructure_override(): void
    {
        $provider = app(InfrastructureProviderRepositoryInterface::class)->create(
            type: 'storage', name: 'Dedicated S3', config: ['key' => 'k', 'secret' => 's', 'bucket' => 'b'],
        );
        $tenant = app(TenantRepositoryInterface::class)->findBySubdomain('testing');

        Livewire::actingAs($this->godAdmin, 'godadmin')
            ->test(Show::class, ['tenantId' => $tenant->id])
            ->set('storageProviderId', $provider->id)
            ->call('saveInfrastructure');

        $withOverride = app(TenantRepositoryInterface::class)->findById($tenant->id);
        $this->assertSame($provider->id, $withOverride->storageProviderId);

        Livewire::actingAs($this->godAdmin, 'godadmin')
            ->test(Show::class, ['tenantId' => $tenant->id])
            ->set('storageProviderId', '')
            ->call('saveInfrastructure');

        $cleared = app(TenantRepositoryInterface::class)->findById($tenant->id);
        $this->assertNull($cleared->storageProviderId);
    }

    public function test_guest_cannot_access_infrastructure_providers(): void
    {
        $this->get('/god/infrastructure-providers')->assertRedirect('/god/login');
    }
}
