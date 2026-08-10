<?php

namespace App\Application\UseCases\GodAdmin;

use App\Application\UseCases\Landlord\LogLandlordAuditUseCase;
use App\Domain\Repositories\AgentProfileRepositoryInterface;

class DeleteAgentProfileUseCase
{
    public function __construct(
        private AgentProfileRepositoryInterface $agentProfileRepository,
        private LogLandlordAuditUseCase $logLandlordAudit,
        private SyncAgentProfilesForAllTenantsOnPlansUseCase $syncAllTenants,
    ) {}

    public function execute(string $actorId, string $profileId): void
    {
        $planIds = $this->agentProfileRepository->getPlanIds($profileId);

        // agent_profile_subscription_plan rows cascade-delete with the
        // profile itself (FK), but tenants' own Assistant rows referencing
        // this agent_profile_id don't know it's gone until this fan-out
        // runs — findByPlanId() naturally excludes the now-deleted profile,
        // so the same sync used for plan-assignment changes correctly
        // deactivates it everywhere, no special-casing needed.
        $this->agentProfileRepository->delete($profileId);

        if ($planIds !== []) {
            $this->syncAllTenants->execute($planIds);
        }

        $this->logLandlordAudit->execute(
            actorId: $actorId,
            action: 'agent_profile_deleted',
            model: 'AgentProfile',
            modelId: $profileId,
        );
    }
}
