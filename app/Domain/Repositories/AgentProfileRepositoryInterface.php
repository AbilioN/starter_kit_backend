<?php

namespace App\Domain\Repositories;

use App\Domain\Entities\AgentProfile;

interface AgentProfileRepositoryInterface
{
    public function findById(string $id): ?AgentProfile;

    public function findAll(): array;

    /**
     * Active agent profiles assigned to a given subscription plan, via the
     * agent_profile_subscription_plan pivot.
     */
    public function findByPlanId(string $planId): array;

    public function create(
        string $name,
        ?string $description,
        ?string $avatar,
        ?string $systemPrompt,
        ?string $model,
        bool $isActive = true,
    ): AgentProfile;

    public function update(
        string $id,
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
    ): AgentProfile;

    public function delete(string $id): void;

    /**
     * Replaces the profile's plan assignments outright — returns the plan
     * ids that were assigned *before* this call, so the caller can fan out
     * a sync to the union of old and new plans (tenants losing access need
     * to be caught up too, not just ones gaining it).
     *
     * @param array<int, string> $planIds
     * @return array<int, string> previously assigned plan ids
     */
    public function syncPlans(string $profileId, array $planIds): array;

    /**
     * @return array<int, string> plan ids currently assigned to this profile
     */
    public function getPlanIds(string $profileId): array;
}
