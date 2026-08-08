<?php

namespace App\Domain\Repositories;

use App\Domain\Entities\Tenant;

interface TenantRepositoryInterface
{
    public function findById(string $id): ?Tenant;
    public function findBySubdomain(string $subdomain): ?Tenant;
    public function findAll(): array;
    public function create(
        string $name,
        string $subdomain,
        string $databaseName,
        ?string $subscriptionPlanId,
        string $createdVia,
        string $status = 'pending'
    ): Tenant;
    public function update(
        string $id,
        ?string $name = null,
        ?string $status = null,
        ?string $subscriptionPlanId = null,
        ?string $themePrimaryColor = null,
        ?string $themeSecondaryColor = null,
        ?string $logoPath = null,
        ?string $broadcastingProviderId = null,
        ?string $storageProviderId = null,
        ?string $aiProviderId = null,
        bool $clearBroadcastingProvider = false,
        bool $clearStorageProvider = false,
        bool $clearAiProvider = false,
    ): Tenant;
}
