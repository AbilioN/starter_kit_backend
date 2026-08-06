<?php

namespace App\Application\UseCases\Public;

use App\Domain\Entities\SubscriptionPlan;
use App\Domain\Repositories\SubscriptionPlanRepositoryInterface;

class GetPublicSubscriptionPlansUseCase
{
    public function __construct(
        private SubscriptionPlanRepositoryInterface $subscriptionPlanRepository,
    ) {}

    public function execute(): array
    {
        return array_map(
            fn (SubscriptionPlan $plan) => $this->toPublicPayload($plan),
            $this->subscriptionPlanRepository->findPublic(),
        );
    }

    public function findBySlug(string $slug): ?array
    {
        $plan = $this->subscriptionPlanRepository->findBySlug($slug);

        if (! $plan || ! $plan->isPublic || ! $plan->isActive) {
            return null;
        }

        return $this->toPublicPayload($plan);
    }

    /**
     * Never expose `limits` or the raw `id` beyond what's needed to submit
     * a signup - this payload is unauthenticated and world-readable.
     */
    private function toPublicPayload(SubscriptionPlan $plan): array
    {
        return [
            'id' => $plan->id,
            'name' => $plan->name,
            'slug' => $plan->slug,
            'price_cents' => $plan->priceCents,
            'features' => $plan->features,
            'tertiary_color' => $plan->tertiaryColor,
            'icon_small_url' => $this->iconUrl($plan->iconPaths['small'] ?? null),
            'icon_medium_url' => $this->iconUrl($plan->iconPaths['medium'] ?? null),
            'icon_large_url' => $this->iconUrl($plan->iconPaths['large'] ?? null),
        ];
    }

    private function iconUrl(?string $path): ?string
    {
        return $path ? asset('storage/'.$path) : null;
    }
}
