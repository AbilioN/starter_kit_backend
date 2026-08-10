<?php

namespace App\Application\UseCases\GodAdmin;

use App\Application\UseCases\Landlord\LogLandlordAuditUseCase;
use App\Domain\Entities\AgentProfile;
use App\Domain\Repositories\AgentProfileRepositoryInterface;

class UpdateAgentProfileUseCase
{
    public function __construct(
        private AgentProfileRepositoryInterface $agentProfileRepository,
        private LogLandlordAuditUseCase $logLandlordAudit,
        private SyncAgentProfilesForAllTenantsOnPlansUseCase $syncAllTenants,
    ) {}

    /**
     * @param array<int, string> $planIds
     */
    public function execute(
        string $actorId,
        string $profileId,
        ?string $name = null,
        ?string $description = null,
        ?string $avatar = null,
        ?string $systemPrompt = null,
        ?string $model = null,
        ?bool $isActive = null,
        bool $clearDescription = false,
        bool $clearAvatar = false,
        bool $clearSystemPrompt = false,
        bool $clearModel = false,
        array $planIds = [],
    ): AgentProfile {
        $profile = $this->agentProfileRepository->update(
            id: $profileId,
            name: $name,
            description: $description,
            avatar: $avatar,
            systemPrompt: $systemPrompt,
            model: $model,
            isActive: $isActive,
            clearDescription: $clearDescription,
            clearAvatar: $clearAvatar,
            clearSystemPrompt: $clearSystemPrompt,
            clearModel: $clearModel,
        );

        // Union of previously-assigned and newly-assigned plans: tenants
        // LOSING access need to be caught up (deactivated) too, not just
        // ones gaining it.
        $previousPlanIds = $this->agentProfileRepository->syncPlans($profileId, $planIds);
        $affectedPlanIds = array_unique(array_merge($previousPlanIds, $planIds));

        if ($affectedPlanIds !== []) {
            $this->syncAllTenants->execute($affectedPlanIds);
        }

        $this->logLandlordAudit->execute(
            actorId: $actorId,
            action: 'agent_profile_updated',
            model: 'AgentProfile',
            modelId: $profile->id,
        );

        return $profile;
    }
}
