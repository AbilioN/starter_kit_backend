<?php

namespace App\Domain\Repositories;

use App\Domain\Entities\SubscriptionPlan;

interface SubscriptionPlanRepositoryInterface
{
    public function findById(string $id): ?SubscriptionPlan;
    public function findBySlug(string $slug): ?SubscriptionPlan;
    public function findAll(): array;
    public function findActive(): array;
    public function create(
        string $name,
        string $slug,
        ?int $priceCents,
        array $features,
        array $limits,
        bool $isActive = true
    ): SubscriptionPlan;
    public function update(
        string $id,
        ?string $name = null,
        ?int $priceCents = null,
        ?array $features = null,
        ?array $limits = null,
        ?bool $isActive = null
    ): SubscriptionPlan;
    public function delete(string $id): void;
}
