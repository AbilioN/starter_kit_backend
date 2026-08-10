<?php

namespace App\Models;

use App\Domain\Entities\AgentProfile as AgentProfileEntity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AgentProfile extends Model
{
    use HasUuids, HasFactory;

    protected $connection = 'landlord';

    protected $fillable = [
        'name',
        'description',
        'avatar',
        'system_prompt',
        'model',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function subscriptionPlans(): BelongsToMany
    {
        return $this->belongsToMany(SubscriptionPlan::class, 'agent_profile_subscription_plan');
    }

    public function toEntity(): AgentProfileEntity
    {
        return new AgentProfileEntity(
            id: $this->id,
            name: $this->name,
            description: $this->description,
            avatar: $this->avatar,
            systemPrompt: $this->system_prompt,
            model: $this->model,
            isActive: $this->is_active,
            createdAt: $this->created_at,
            updatedAt: $this->updated_at,
        );
    }
}
