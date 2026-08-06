<?php

namespace Tests\Feature\Tenant;

use App\Domain\Repositories\SubscriptionPlanRepositoryInterface;
use App\Domain\Repositories\TenantRepositoryInterface;
use App\Models\Admin;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\AdminRolePermissionSeeder;
use Database\Seeders\AdminSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TenantTestCase;

class PlanLimitEnforcementTest extends TenantTestCase
{
    private Admin $superAdmin;
    private Admin $adminWithAllPermissions;

    public function setUp(): void
    {
        parent::setUp();
        $this->actingAsTenant();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(AdminSeeder::class);
        $this->seed(AdminRolePermissionSeeder::class);

        $this->superAdmin = Admin::where('is_super_admin', true)->first();

        $this->adminWithAllPermissions = Admin::factory()->create([
            'name' => 'Admin With All Permissions',
            'email' => 'allperms@test.com',
            'is_active' => true,
            'is_super_admin' => false,
        ]);

        $role = Role::where('slug', 'admin')->first();
        $role->permissions()->sync(Permission::all()->pluck('id'));
        $this->adminWithAllPermissions->roles()->attach($role->id, [
            'assigned_at' => now(),
            'assigned_by' => $this->superAdmin->id,
        ]);
    }

    private function seedLimit(string $key, int $value): void
    {
        Setting::create([
            'key' => "limits.{$key}",
            'value' => (string) $value,
            'type' => 'integer',
            'group' => 'limits',
            'label' => $key,
        ]);
    }

    // --- Seeding / re-sync -------------------------------------------------

    public function test_provisioning_seeds_limits_from_the_chosen_plan(): void
    {
        $plan = app(SubscriptionPlanRepositoryInterface::class)->create(
            name: 'Pro', slug: 'pro-limits', priceCents: 9900, features: [],
            limits: ['max_admins' => 7, 'max_users' => 50, 'max_storage_mb' => 2048],
        );

        $this->artisan('tenant:provision', [
            'name' => 'Limits Co',
            'subdomain' => 'limitsco',
            '--plan' => $plan->id,
            '--admin-email' => 'owner@limitsco.test',
            '--admin-password' => 'super-secret',
        ])->assertExitCode(0);

        $this->assertSame('7', Setting::where('key', 'limits.max_admins')->first()?->value);
        $this->assertSame('50', Setting::where('key', 'limits.max_users')->first()?->value);
        $this->assertSame('2048', Setting::where('key', 'limits.max_storage_mb')->first()?->value);
    }

    public function test_changing_plan_resyncs_limits(): void
    {
        $this->seedLimit('max_admins', 3);

        $owner = Admin::factory()->create(['is_tenant_owner' => true, 'is_active' => true]);
        $newPlan = app(SubscriptionPlanRepositoryInterface::class)->create(
            name: 'Enterprise', slug: 'enterprise-limits', priceCents: 29900, features: [],
            limits: ['max_admins' => 25],
        );

        $token = $owner->createToken('t')->plainTextToken;

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->patchJson('/api/admin/tenant/subscription-plan', ['subscription_plan_id' => $newPlan->id])
            ->assertStatus(200);

        $this->assertSame('25', Setting::where('key', 'limits.max_admins')->first()->value);
    }

    // --- max_admins ----------------------------------------------------

    public function test_admin_creation_is_blocked_at_the_plan_limit(): void
    {
        // AdminSeeder creates 21 admins (1 super + 20 fake) + adminWithAllPermissions = 22.
        $this->seedLimit('max_admins', Admin::count());

        $token = $this->superAdmin->createToken('t')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/admin/admins', [
                'name' => 'One Too Many',
                'email' => 'toomany@test.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ]);

        $response->assertStatus(402);
        $this->assertDatabaseMissing('admins', ['email' => 'toomany@test.com']);
    }

    public function test_admin_creation_succeeds_under_the_plan_limit(): void
    {
        $this->seedLimit('max_admins', Admin::count() + 1);

        $token = $this->superAdmin->createToken('t')->plainTextToken;

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/admin/admins', [
                'name' => 'Room To Spare',
                'email' => 'roomtospare@test.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ])
            ->assertStatus(201);
    }

    public function test_admin_creation_is_unlimited_when_no_limit_is_configured(): void
    {
        // No `limits.max_admins` setting seeded at all.
        $token = $this->superAdmin->createToken('t')->plainTextToken;

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/admin/admins', [
                'name' => 'No Limit',
                'email' => 'nolimit@test.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ])
            ->assertStatus(201);
    }

    // --- max_users -------------------------------------------------------

    public function test_user_registration_is_blocked_at_the_plan_limit(): void
    {
        User::factory()->count(2)->create();
        $this->seedLimit('max_users', 2);

        $response = $this->postJson('/api/register', [
            'name' => 'Late User',
            'email' => 'late@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(402);
        $this->assertDatabaseMissing('users', ['email' => 'late@test.com']);
    }

    public function test_user_registration_succeeds_under_the_plan_limit(): void
    {
        $this->seedLimit('max_users', 10);

        $this->postJson('/api/register', [
            'name' => 'Early User',
            'email' => 'early@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(201);
    }

    // --- max_storage_mb ----------------------------------------------------

    public function test_file_upload_is_blocked_when_it_would_exceed_the_storage_limit(): void
    {
        Storage::fake('local');
        $this->seedLimit('max_storage_mb', 1); // 1 MB total

        $token = $this->adminWithAllPermissions->createToken('t')->plainTextToken;
        $file = UploadedFile::fake()->create('big.pdf', 2000); // 2000 KB ≈ 1.95 MB

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/admin/files', ['file' => $file]);

        $response->assertStatus(402);
    }

    public function test_file_upload_succeeds_within_the_storage_limit(): void
    {
        Storage::fake('local');
        $this->seedLimit('max_storage_mb', 10);

        $token = $this->adminWithAllPermissions->createToken('t')->plainTextToken;
        $file = UploadedFile::fake()->create('small.pdf', 100); // 100 KB

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/admin/files', ['file' => $file])
            ->assertStatus(201);
    }
}
