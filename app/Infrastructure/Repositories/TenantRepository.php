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
        ?string $themePrimaryColor = null,
        ?string $themeSecondaryColor = null,
        ?string $logoPath = null
    ): Tenant {
        $tenant = TenantModel::findOrFail($id);

        $updateData = array_filter([
            'name' => $name,
            'status' => $status,
            'subscription_plan_id' => $subscriptionPlanId,
            'theme_primary_color' => $themePrimaryColor,
            'theme_secondary_color' => $themeSecondaryColor,
            'logo_path' => $logoPath,
        ], fn ($value) => $value !== null);

        $tenant->update($updateData);

        return $tenant->fresh()->toEntity();
    }
}
