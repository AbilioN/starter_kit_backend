<?php

namespace App\Livewire;

use App\Domain\Repositories\SubscriptionPlanRepositoryInterface;
use App\Domain\Repositories\TenantRepositoryInterface;
use Livewire\Component;

class Dashboard extends Component
{
    public function render()
    {
        $tenants = app(TenantRepositoryInterface::class)->findAll();
        $plans = app(SubscriptionPlanRepositoryInterface::class)->findAll();

        return view('livewire.dashboard', [
            'tenantCount' => count($tenants),
            'activeTenantCount' => count(array_filter($tenants, fn ($t) => $t->isActive())),
            'planCount' => count($plans),
        ])->layout('layouts.god');
    }
}
