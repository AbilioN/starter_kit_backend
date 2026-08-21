<?php

namespace App\Application\UseCases\GodAdmin;

use App\Application\UseCases\Landlord\LogLandlordAuditUseCase;
use App\Domain\Entities\SubscriptionPlan;
use App\Domain\Repositories\SubscriptionPlanRepositoryInterface;

class CreateSubscriptionPlanUseCase
{
    public function __construct(
        private SubscriptionPlanRepositoryInterface $subscriptionPlanRepository,
        private LogLandlordAuditUseCase $logLandlordAudit,
    ) {}

    public function execute(
        string $actorId,
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
        ?string $aiProviderId = null,
        ?string $backupProviderId = null,
    ): SubscriptionPlan {
        $plan = $this->subscriptionPlanRepository->create(
            name: $name,
            slug: $slug,
            priceCents: $priceCents,
            features: $features,
            limits: $limits,
            isActive: $isActive,
            isPublic: $isPublic,
            tertiaryColor: $tertiaryColor,
            iconPaths: $iconPaths,
            broadcastingProviderId: $broadcastingProviderId,
            storageProviderId: $storageProviderId,
            aiProviderId: $aiProviderId,
            backupProviderId: $backupProviderId,
        );

        $this->logLandlordAudit->execute(
            actorId: $actorId,
            action: 'subscription_plan_created',
            model: 'SubscriptionPlan',
            modelId: $plan->id,
        );

        return $plan;
    }
}
