<?php

namespace App\Livewire\SubscriptionPlans;

use App\Domain\Repositories\SubscriptionPlanRepositoryInterface;
use Livewire\Component;

class Index extends Component
{
    public function render()
    {
        $plans = app(SubscriptionPlanRepositoryInterface::class)->findAll();

        return view('livewire.subscription-plans.index', ['plans' => $plans])
            ->layout('layouts.god');
    }
}
