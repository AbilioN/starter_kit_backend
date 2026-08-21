<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Entities\Tenant;
use App\Domain\Repositories\TenantRepositoryInterface;
use App\Models\Tenant as TenantModel;

class TenantRepository implements TenantRepositoryInterface
{
    public function findById(string $id): ?Tenant
    {
        $tenant = TenantModel::find($id);

        return $tenant?->toEntity();
    }

    public function findBySubdomain(string $subdomain): ?Tenant
    {
        $tenant = TenantModel::where('subdomain', $subdomain)->first();

        return $tenant?->toEntity();
    }

    public function findAll(): array
    {
        return TenantModel::all()
            ->map(fn (TenantModel $tenant) => $tenant->toEntity())
            ->all();
    }

    public function findBySubscriptionPlanId(string $planId): array
    {
        return TenantModel::where('subscription_plan_id', $planId)->get()
            ->map(fn (TenantModel $tenant) => $tenant->toEntity())
            ->all();
    }

    public function create(
        string $name,
        string $subdomain,
        string $databaseName,
        ?string $subscriptionPlanId,
        string $createdVia,
        string $status = 'pending'
    ): Tenant {
        $tenant = TenantModel::create([
            'name' => $name,
            'subdomain' => $subdomain,
            'database_name' => $databaseName,
            'subscription_plan_id' => $subscriptionPlanId,
            'created_via' => $createdVia,
            'status' => $status,
        ]);

        return $tenant->toEntity();
    }

    public function update(
        string $id,
        ?string $name = null,
        ?string $status = null,
        ?string $subscriptionPlanId = null,
        // Only ever written by a restore: pointing a tenant at a different
        // database is the flip that makes RestoreTenantBackupUseCase
        // reversible, and it is not something any other caller should do.
        ?string $databaseName = null,
        ?string $themePrimaryColor = null,
        ?string $themeSecondaryColor = null,
        ?string $logoPath = null,
        ?string $broadcastingProviderId = null,
        ?string $storageProviderId = null,
        ?string $aiProviderId = null,
        ?string $backupProviderId = null,
        bool $clearBroadcastingProvider = false,
        bool $clearStorageProvider = false,
        bool $clearAiProvider = false,
        bool $clearBackupProvider = false,
    ): Tenant {
        $tenant = TenantModel::findOrFail($id);

        $updateData = array_filter([
            'name' => $name,
            'status' => $status,
            'subscription_plan_id' => $subscriptionPlanId,
            'database_name' => $databaseName,
            'theme_primary_color' => $themePrimaryColor,
            'theme_secondary_color' => $themeSecondaryColor,
            'logo_path' => $logoPath,
            'broadcasting_provider_id' => $broadcastingProviderId,
            'storage_provider_id' => $storageProviderId,
            'ai_provider_id' => $aiProviderId,
            'backup_provider_id' => $backupProviderId,
        ], fn ($value) => $value !== null);

        // Same "null = don't touch" convention as SubscriptionPlanRepository
        // — the explicit clear flags are the only way to actually null out
        // a provider override (fall back to inheriting the plan's default).
        if ($clearBroadcastingProvider) {
            $updateData['broadcasting_provider_id'] = null;
        }
        if ($clearStorageProvider) {
            $updateData['storage_provider_id'] = null;
        }
        if ($clearAiProvider) {
            $updateData['ai_provider_id'] = null;
        }
        if ($clearBackupProvider) {
            $updateData['backup_provider_id'] = null;
        }

        $tenant->update($updateData);

        return $tenant->fresh()->toEntity();
    }
}
