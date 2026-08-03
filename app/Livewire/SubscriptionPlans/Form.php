<?php

namespace App\Livewire\SubscriptionPlans;

use App\Application\UseCases\GodAdmin\CreateSubscriptionPlanUseCase;
use App\Application\UseCases\GodAdmin\UpdateSubscriptionPlanUseCase;
use App\Domain\Repositories\SubscriptionPlanRepositoryInterface;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Form extends Component
{
    public ?string $planId = null;

    public string $name = '';

    public string $slug = '';

    public ?int $priceCents = null;

    public bool $isActive = true;

    public bool $featureChat = false;

    public bool $featureFileUpload = false;

    public bool $featureNotifications = false;

    public bool $featureAiAgent = false;

    public int $maxAdmins = 5;

    public int $maxUsers = 100;

    public int $maxStorageMb = 1024;

    public function mount(?string $planId = null): void
    {
        if (! $planId) {
            return;
        }

        $plan = app(SubscriptionPlanRepositoryInterface::class)->findById($planId);

        if (! $plan) {
            abort(404);
        }

        $this->planId = $plan->id;
        $this->name = $plan->name;
        $this->slug = $plan->slug;
        $this->priceCents = $plan->priceCents;
        $this->isActive = $plan->isActive;
        $this->featureChat = (bool) ($plan->features['chat'] ?? false);
        $this->featureFileUpload = (bool) ($plan->features['file_upload'] ?? false);
        $this->featureNotifications = (bool) ($plan->features['notifications'] ?? false);
        $this->featureAiAgent = (bool) ($plan->features['ai_agent'] ?? false);
        $this->maxAdmins = (int) ($plan->limits['max_admins'] ?? 5);
        $this->maxUsers = (int) ($plan->limits['max_users'] ?? 100);
        $this->maxStorageMb = (int) ($plan->limits['max_storage_mb'] ?? 1024);
    }

    public function save(
        CreateSubscriptionPlanUseCase $createSubscriptionPlan,
        UpdateSubscriptionPlanUseCase $updateSubscriptionPlan,
    ): void {
        $this->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|regex:/^[a-z0-9-]+$/',
            'priceCents' => 'nullable|integer|min:0',
        ]);

        $actorId = Auth::guard('godadmin')->id();
        $features = [
            'chat' => $this->featureChat,
            'file_upload' => $this->featureFileUpload,
            'notifications' => $this->featureNotifications,
            'ai_agent' => $this->featureAiAgent,
        ];
        $limits = [
            'max_admins' => $this->maxAdmins,
            'max_users' => $this->maxUsers,
            'max_storage_mb' => $this->maxStorageMb,
        ];

        if ($this->planId) {
            $updateSubscriptionPlan->execute(
                actorId: $actorId,
                planId: $this->planId,
                name: $this->name,
                priceCents: $this->priceCents,
                features: $features,
                limits: $limits,
                isActive: $this->isActive,
            );
        } else {
            $createSubscriptionPlan->execute(
                actorId: $actorId,
                name: $this->name,
                slug: $this->slug,
                priceCents: $this->priceCents,
                features: $features,
                limits: $limits,
                isActive: $this->isActive,
            );
        }

        $this->redirect('/god/subscription-plans', navigate: false);
    }

    public function render()
    {
        return view('livewire.subscription-plans.form')->layout('layouts.god');
    }
}
