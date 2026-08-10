<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Entities\AgentProfile;
use App\Domain\Repositories\AgentProfileRepositoryInterface;
use App\Models\AgentProfile as AgentProfileModel;

class AgentProfileRepository implements AgentProfileRepositoryInterface
{
    public function findById(string $id): ?AgentProfile
    {
        return AgentProfileModel::find($id)?->toEntity();
    }

    public function findAll(): array
    {
        return AgentProfileModel::orderBy('name')->get()
            ->map(fn (AgentProfileModel $profile) => $profile->toEntity())
            ->all();
    }

    public function findByPlanId(string $planId): array
    {
        return AgentProfileModel::where('is_active', true)
            ->whereHas('subscriptionPlans', fn ($query) => $query->where('subscription_plans.id', $planId))
            ->orderBy('name')
            ->get()
            ->map(fn (AgentProfileModel $profile) => $profile->toEntity())
            ->all();
    }

    public function create(
        string $name,
        ?string $description,
        ?string $avatar,
        ?string $systemPrompt,
        ?string $model,
        bool $isActive = true,
    ): AgentProfile {
        $profile = AgentProfileModel::create([
            'name' => $name,
            'description' => $description,
            'avatar' => $avatar,
            'system_prompt' => $systemPrompt,
            'model' => $model,
            'is_active' => $isActive,
        ]);

        return $profile->toEntity();
    }

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
    ): AgentProfile {
        $profile = AgentProfileModel::findOrFail($id);

        $updateData = array_filter([
            'name' => $name,
            'description' => $description,
            'avatar' => $avatar,
            'system_prompt' => $systemPrompt,
            'model' => $model,
            'is_active' => $isActive,
        ], fn ($value) => $value !== null);

        // Same "null = don't touch, explicit clear flag = actually null it
        // out" convention as InfrastructureProvider/Tenant/SubscriptionPlan.
        if ($clearDescription) {
            $updateData['description'] = null;
        }
        if ($clearAvatar) {
            $updateData['avatar'] = null;
        }
        if ($clearSystemPrompt) {
            $updateData['system_prompt'] = null;
        }
        if ($clearModel) {
            $updateData['model'] = null;
        }

        $profile->update($updateData);

        return $profile->fresh()->toEntity();
    }

    public function delete(string $id): void
    {
        AgentProfileModel::findOrFail($id)->delete();
    }

    public function syncPlans(string $profileId, array $planIds): array
    {
        $profile = AgentProfileModel::findOrFail($profileId);

        $previousPlanIds = $profile->subscriptionPlans()->pluck('subscription_plans.id')->all();

        $profile->subscriptionPlans()->sync($planIds);

        return $previousPlanIds;
    }

    public function getPlanIds(string $profileId): array
    {
        return AgentProfileModel::findOrFail($profileId)->subscriptionPlans()->pluck('subscription_plans.id')->all();
    }
}
