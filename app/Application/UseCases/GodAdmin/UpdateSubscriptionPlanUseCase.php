<?php

namespace App\Application\UseCases\GodAdmin;

use App\Application\UseCases\Landlord\LogLandlordAuditUseCase;
use App\Domain\Entities\SubscriptionPlan;
use App\Domain\Repositories\SubscriptionPlanRepositoryInterface;

class UpdateSubscriptionPlanUseCase
{
    public function __construct(
        private SubscriptionPlanRepositoryInterface $subscriptionPlanRepository,
        private LogLandlordAuditUseCase $logLandlordAudit,
    ) {}

    public function execute(
        string $actorId,
        string $planId,
        ?string $name = null,
        ?int $priceCents = null,
        ?array $features = null,
        ?array $limits = null,
        ?bool $isActive = null,
        ?bool $isPublic = null,
        ?string $tertiaryColor = null,
        ?array $iconPaths = null,
    ): SubscriptionPlan {
        $plan = $this->subscriptionPlanRepository->update(
            id: $planId,
            name: $name,
            priceCents: $priceCents,
            features: $features,
            limits: $limits,
            isActive: $isActive,
            isPublic: $isPublic,
            tertiaryColor: $tertiaryColor,
            iconPaths: $iconPaths,
        );

        $this->logLandlordAudit->execute(
            actorId: $actorId,
            action: 'subscription_plan_updated',
            model: 'SubscriptionPlan',
            modelId: $plan->id,
        );

        return $plan;
    }
}
