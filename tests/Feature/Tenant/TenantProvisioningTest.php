<?php

namespace Tests\Feature\Tenant;

use App\Domain\Repositories\SubscriptionPlanRepositoryInterface;
use App\Models\Admin;
use App\Models\Setting;
use App\Models\Tenant;
use Tests\TenantTestCase;

class TenantProvisioningTest extends TenantTestCase
{
    public function test_artisan_command_provisions_a_working_tenant(): void
    {
        $this->artisan('tenant:provision', [
            'name' => 'Acme Inc',
            'subdomain' => 'acme',
            '--admin-email' => 'owner@acme.test',
            '--admin-password' => 'super-secret',
        ])->assertExitCode(0);

        $tenant = Tenant::on('landlord')->where('subdomain', 'acme')->first();
        $this->assertNotNull($tenant);
        $this->assertSame('active', $tenant->status);
        $this->assertSame('godadmin', $tenant->created_via);

        // database.default is now 'tenant', pointed at the freshly
        // provisioned database - unqualified queries resolve there.
        $owner = Admin::where('email', 'owner@acme.test')->first();
        $this->assertNotNull($owner);
        $this->assertTrue($owner->is_tenant_owner);
        $this->assertTrue($owner->is_super_admin);
    }

    public function test_provisioning_rejects_a_duplicate_subdomain(): void
    {
        $this->artisan('tenant:provision', [
            'name' => 'Acme Inc',
            'subdomain' => 'acme',
            '--admin-email' => 'owner@acme.test',
            '--admin-password' => 'super-secret',
        ])->assertExitCode(0);

        $this->artisan('tenant:provision', [
            'name' => 'Acme Again',
            'subdomain' => 'acme',
            '--admin-email' => 'other@acme.test',
            '--admin-password' => 'super-secret',
        ])->assertExitCode(1);
    }

    public function test_provisioning_seeds_features_from_the_chosen_plan(): void
    {
        $plan = app(SubscriptionPlanRepositoryInterface::class)->create(
            name: 'Pro',
            slug: 'pro',
            priceCents: 9900,
            features: ['chat' => true, 'file_upload' => false],
            limits: ['max_admins' => 10],
        );

        $this->artisan('tenant:provision', [
            'name' => 'Beta Co',
            'subdomain' => 'beta',
            '--plan' => $plan->id,
            '--admin-email' => 'owner@beta.test',
            '--admin-password' => 'super-secret',
        ])->assertExitCode(0);

        $chatSetting = Setting::where('key', 'features.chat')->first();
        $fileUploadSetting = Setting::where('key', 'features.file_upload')->first();

        $this->assertNotNull($chatSetting);
        $this->assertSame('1', $chatSetting->value);
        $this->assertNotNull($fileUploadSetting);
        $this->assertSame('0', $fileUploadSetting->value);
    }

    public function test_self_service_signup_provisions_a_tenant(): void
    {
        $response = $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->post('/signup', [
            'name' => 'Self Serve Co',
            'subdomain' => 'selfserve',
            'admin_email' => 'owner@selfserve.test',
            'admin_password' => 'super-secret',
            'admin_password_confirmation' => 'super-secret',
        ]);

        $response->assertRedirect();

        $tenant = Tenant::on('landlord')->where('subdomain', 'selfserve')->first();
        $this->assertNotNull($tenant);
        $this->assertSame('self_service', $tenant->created_via);
    }

    public function test_self_service_signup_rejects_invalid_subdomain(): void
    {
        $response = $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->post('/signup', [
            'name' => 'Bad Co',
            'subdomain' => 'Not Valid!',
            'admin_email' => 'owner@bad.test',
            'admin_password' => 'super-secret',
            'admin_password_confirmation' => 'super-secret',
        ]);

        $response->assertSessionHasErrors('subdomain');
    }
}
