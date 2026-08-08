<?php

namespace App\Domain\Repositories;

use App\Domain\Entities\SubscriptionPlan;

interface SubscriptionPlanRepositoryInterface
{
    public function findById(string $id): ?SubscriptionPlan;
    public function findBySlug(string $slug): ?SubscriptionPlan;
    public function findAll(): array;
    public function findActive(): array;
    public function findPublic(): array;
    public function create(
        string $name,
        string $slug,
        ?int $priceCents,
        array $features,
        array $limits,
        bool $isActive = true,
        bool $isPublic = false,
        ?string $tertiaryColor = null,
        ?array $iconPaths = null,
        ?string $broadcastingProviderId = null,
        ?string $storageProviderId = null,
    ): SubscriptionPlan;
    public function update(
        string $id,
        ?string $name = null,
        ?int $priceCents = null,
        ?array $features = null,
        ?array $limits = null,
        ?bool $isActive = null,
        ?bool $isPublic = null,
        ?string $tertiaryColor = null,
        ?array $iconPaths = null,
        ?string $broadcastingProviderId = null,
        ?string $storageProviderId = null,
        bool $clearBroadcastingProvider = false,
        bool $clearStorageProvider = false,
    ): SubscriptionPlan;
    public function delete(string $id): void;
}
