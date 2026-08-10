<?php

namespace App\Application\UseCases\GodAdmin;

use App\Application\UseCases\Landlord\LogLandlordAuditUseCase;
use App\Domain\Entities\AgentProfile;
use App\Domain\Repositories\AgentProfileRepositoryInterface;

class CreateAgentProfileUseCase
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
        string $name,
        ?string $description,
        ?string $avatar,
        ?string $systemPrompt,
        ?string $model,
        bool $isActive,
        array $planIds = [],
    ): AgentProfile {
        $profile = $this->agentProfileRepository->create(
            name: $name,
            description: $description,
            avatar: $avatar,
            systemPrompt: $systemPrompt,
            model: $model,
            isActive: $isActive,
        );

        if ($planIds !== []) {
            $this->agentProfileRepository->syncPlans($profile->id, $planIds);
            $this->syncAllTenants->execute($planIds);
        }

        $this->logLandlordAudit->execute(
            actorId: $actorId,
            action: 'agent_profile_created',
            model: 'AgentProfile',
            modelId: $profile->id,
        );

        return $profile;
    }
}
