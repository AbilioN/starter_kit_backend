<?php

namespace App\Application\UseCases\Tenant;

use App\Application\UseCases\Landlord\RecordMockPaymentUseCase;
use App\Domain\Entities\Tenant;
use App\Domain\Repositories\SubscriptionPlanRepositoryInterface;
use App\Domain\Repositories\TenantRepositoryInterface;
use App\Domain\Services\TenantProvisioningServiceInterface;
use App\Models\Admin;
use App\Models\Setting;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use DomainException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProvisionTenantUseCase
{
    public function __construct(
        private TenantRepositoryInterface $tenantRepository,
        private SubscriptionPlanRepositoryInterface $subscriptionPlanRepository,
        private TenantProvisioningServiceInterface $provisioningService,
        private RecordMockPaymentUseCase $recordMockPayment,
    ) {}

    public function execute(
        string $name,
        string $subdomain,
        ?string $subscriptionPlanId,
        string $createdVia,
        string $adminEmail,
        string $adminPassword,
        ?string $adminName = null,
        ?string $themePrimaryColor = null,
        ?string $themeSecondaryColor = null,
        ?string $logoPath = null,
    ): Tenant {
        if ($this->tenantRepository->findBySubdomain($subdomain)) {
            throw new DomainException("Subdomain '{$subdomain}' is already taken.");
        }

        $databaseName = $this->buildDatabaseName($subdomain);

        $this->provisioningService->createDatabase($databaseName);

        $tenant = $this->tenantRepository->create(
            name: $name,
            subdomain: $subdomain,
            databaseName: $databaseName,
            subscriptionPlanId: $subscriptionPlanId,
            createdVia: $createdVia,
            status: 'active',
        );

        if ($themePrimaryColor || $themeSecondaryColor || $logoPath) {
            $tenant = $this->tenantRepository->update(
                id: $tenant->id,
                themePrimaryColor: $themePrimaryColor,
                themeSecondaryColor: $themeSecondaryColor,
                logoPath: $logoPath,
            );
        }

        $this->pointTenantConnectionAt($databaseName);

        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/tenant',
            '--force' => true,
        ]);

        (new RoleSeeder())->run();
        (new PermissionSeeder())->run();

        Admin::create([
            'name' => $adminName ?? "{$name} Owner",
            'email' => $adminEmail,
            'password' => $adminPassword,
            'is_active' => true,
            'is_super_admin' => true,
            'is_tenant_owner' => true,
        ]);

        if ($subscriptionPlanId) {
            $this->seedFeaturesFromPlan($subscriptionPlanId);
            $this->seedLimitsFromPlan($subscriptionPlanId);
            $this->recordMockPayment->execute($tenant->id, $subscriptionPlanId, trigger: 'signup');
        }

        return $tenant;
    }

    private function buildDatabaseName(string $subdomain): string
    {
        $slug = str_replace('-', '_', $subdomain);

        if (config('database.connections.tenant.driver') === 'sqlite') {
            return storage_path("app/sqlite/tenant-{$subdomain}-".Str::random(8).'.sqlite');
        }

        return "starter_kit_tenant_{$slug}";
    }

    private function pointTenantConnectionAt(string $databaseName): void
    {
        config(['database.connections.tenant.database' => $databaseName]);
        config(['database.default' => 'tenant']);
        DB::purge('tenant');
    }

    private function seedFeaturesFromPlan(string $subscriptionPlanId): void
    {
        $plan = $this->subscriptionPlanRepository->findById($subscriptionPlanId);

        if (! $plan) {
            return;
        }

        foreach ($plan->features as $key => $value) {
            Setting::create([
                'key' => "features.{$key}",
                'value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value,
                'type' => is_bool($value) ? 'boolean' : 'string',
                'group' => 'features',
                'label' => Str::headline($key),
            ]);
        }
    }

    private function seedLimitsFromPlan(string $subscriptionPlanId): void
    {
        $plan = $this->subscriptionPlanRepository->findById($subscriptionPlanId);

        if (! $plan) {
            return;
        }

        foreach ($plan->limits as $key => $value) {
            // null = unlimited (e.g. max_storage_mb) - skip creating the
            // setting row entirely rather than storing an empty/zero value,
            // since enforcement code (UploadFileUseCase et al.) already
            // treats "no such setting" as no cap.
            if ($value === null) {
                continue;
            }

            Setting::create([
                'key' => "limits.{$key}",
                'value' => (string) $value,
                'type' => 'integer',
                'group' => 'limits',
                'label' => Str::headline($key),
            ]);
        }
    }
}
