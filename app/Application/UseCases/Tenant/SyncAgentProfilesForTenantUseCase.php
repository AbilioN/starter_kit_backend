<?php

namespace App\Application\UseCases\Tenant;

use App\Domain\Repositories\AgentProfileRepositoryInterface;
use App\Models\Assistant;

/**
 * Syncs which agent profiles are activated as chat participants
 * (App\Models\Assistant rows) on the CURRENT tenant connection, based on
 * whichever AgentProfiles are assigned to a subscription plan. Only ever
 * touches Assistant rows it created itself (agent_profile_id set) - a
 * manually seeded/created assistant (agent_profile_id null, e.g.
 * AssistantSeeder's demo rows) is never touched.
 *
 * Only syncs EXISTENCE/availability (create/deactivate) - the actual
 * system_prompt/model content is read live from the landlord agent_profiles
 * table at OpenAI-request time (see ProcessOpenAIRequest::findActiveAssistant()),
 * never copied here, so editing a profile's prompt takes effect immediately
 * without needing a re-sync.
 */
class SyncAgentProfilesForTenantUseCase
{
    public function __construct(
        private AgentProfileRepositoryInterface $agentProfileRepository,
    ) {}

    public function execute(string $subscriptionPlanId): void
    {
        $profiles = $this->agentProfileRepository->findByPlanId($subscriptionPlanId);
        $activeProfileIds = [];

        foreach ($profiles as $profile) {
            Assistant::updateOrCreate(
                ['agent_profile_id' => $profile->id],
                [
                    'name' => $profile->name,
                    'description' => $profile->description,
                    'avatar' => $profile->avatar,
                    'is_active' => true,
                ],
            );

            $activeProfileIds[] = $profile->id;
        }

        // whereNotIn() with an empty array matches everything (no
        // exclusions) - correct here too: a plan with zero assigned
        // profiles should deactivate every profile-linked assistant.
        Assistant::whereNotNull('agent_profile_id')
            ->whereNotIn('agent_profile_id', $activeProfileIds)
            ->update(['is_active' => false]);
    }
}
