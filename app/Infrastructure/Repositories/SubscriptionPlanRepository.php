<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Entities\SubscriptionPlan;
use App\Domain\Repositories\SubscriptionPlanRepositoryInterface;
use App\Models\SubscriptionPlan as SubscriptionPlanModel;

class SubscriptionPlanRepository implements SubscriptionPlanRepositoryInterface
{
    public function findById(string $id): ?SubscriptionPlan
    {
        $plan = SubscriptionPlanModel::find($id);

        return $plan?->toEntity();
    }

    public function findBySlug(string $slug): ?SubscriptionPlan
    {
        $plan = SubscriptionPlanModel::where('slug', $slug)->first();

        return $plan?->toEntity();
    }

    public function findAll(): array
    {
        return SubscriptionPlanModel::all()
            ->map(fn (SubscriptionPlanModel $plan) => $plan->toEntity())
            ->all();
    }

    public function findActive(): array
    {
        return SubscriptionPlanModel::where('is_active', true)->get()
            ->map(fn (SubscriptionPlanModel $plan) => $plan->toEntity())
            ->all();
    }

    public function findPublic(): array
    {
        return SubscriptionPlanModel::where('is_active', true)->where('is_public', true)->get()
            ->map(fn (SubscriptionPlanModel $plan) => $plan->toEntity())
            ->all();
    }

    public function create(
        string $name,
        string $slug,
        ?int $priceCents,
        array $features,
        array $limits,
        bool $isActive = true,
        bool $isPublic = false,
        ?string $tertiaryColor = null,
        ?array $iconPaths = null
    ): SubscriptionPlan {
        $plan = SubscriptionPlanModel::create([
            'name' => $name,
            'slug' => $slug,
            'price_cents' => $priceCents,
            'features' => $features,
            'limits' => $limits,
            'is_active' => $isActive,
            'is_public' => $isPublic,
            'tertiary_color' => $tertiaryColor,
            'icon_paths' => $iconPaths,
        ]);

        return $plan->toEntity();
    }

    public function update(
        string $id,
        ?string $name = null,
        ?int $priceCents = null,
        ?array $features = null,
        ?array $limits = null,
        ?bool $isActive = null,
        ?bool $isPublic = null,
        ?string $tertiaryColor = null,
        ?array $iconPaths = null
    ): SubscriptionPlan {
        $plan = SubscriptionPlanModel::findOrFail($id);

        $updateData = array_filter([
            'name' => $name,
            'price_cents' => $priceCents,
            'features' => $features,
            'limits' => $limits,
            'is_active' => $isActive,
            'is_public' => $isPublic,
            'tertiary_color' => $tertiaryColor,
            'icon_paths' => $iconPaths,
        ], fn ($value) => $value !== null);

        $plan->update($updateData);

        return $plan->fresh()->toEntity();
    }

    public function delete(string $id): void
    {
        SubscriptionPlanModel::findOrFail($id)->delete();
    }
}
