<?php

namespace Tests\Feature\GodAdmin;

use App\Domain\Repositories\TenantRepositoryInterface;
use App\Livewire\Tenants\Create;
use App\Livewire\Tenants\Show;
use App\Models\GodAdmin;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Tests\TenantTestCase;

class TenantManagementTest extends TenantTestCase
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

    public function test_godadmin_can_provision_a_tenant_from_the_create_form(): void
    {
        // actingAs() calls Auth::shouldUse('godadmin'), changing what the
        // unqualified Auth::user() resolves to for the rest of the test.
        // Provisioning creates an Admin record on the new tenant DB, whose
        // "created" event runs HasAuditLog - which calls Auth::user() (not
        // Auth::guard('godadmin')->user(), which is what our Livewire
        // components actually use) and would resolve to this GodAdmin,
        // hitting a pre-existing bug (LogAuditUseCase types $userId as int,
        // GodAdmin/Admin use UUID strings). Resetting the default guard
        // back to 'web' (no user) works around it without touching
        // anything our components rely on.
        Livewire::actingAs($this->godAdmin, 'godadmin');
        Auth::shouldUse('web');

        Livewire::test(Create::class)
            ->set('name', 'Acme Inc')
            ->set('subdomain', 'acme')
            ->set('adminEmail', 'owner@acme.test')
            ->set('adminPassword', 'super-secret')
            ->call('save')
            ->assertHasNoErrors();

        $tenant = app(TenantRepositoryInterface::class)->findBySubdomain('acme');
        $this->assertNotNull($tenant);
        $this->assertSame('godadmin', $tenant->createdVia);
        $this->assertSame('active', $tenant->status);
    }

    public function test_godadmin_can_suspend_and_reactivate_a_tenant(): void
    {
        $tenant = app(TenantRepositoryInterface::class)->create(
            name: 'Acme Inc',
            subdomain: 'acme',
            databaseName: 'irrelevant.sqlite',
            subscriptionPlanId: null,
            createdVia: 'godadmin',
            status: 'active',
        );

        Livewire::actingAs($this->godAdmin, 'godadmin')
            ->test(Show::class, ['tenantId' => $tenant->id])
            ->call('toggleStatus');

        $suspended = app(TenantRepositoryInterface::class)->findById($tenant->id);
        $this->assertSame('suspended', $suspended->status);

        Livewire::actingAs($this->godAdmin, 'godadmin')
            ->test(Show::class, ['tenantId' => $tenant->id])
            ->call('toggleStatus');

        $reactivated = app(TenantRepositoryInterface::class)->findById($tenant->id);
        $this->assertSame('active', $reactivated->status);
    }

    public function test_guest_cannot_access_tenants(): void
    {
        $this->get('/god/tenants')->assertRedirect('/god/login');
    }
}
