<?php

namespace App\Livewire;

use App\Application\UseCases\GodAdmin\GenerateFinancialReportUseCase;
use App\Domain\Repositories\SubscriptionPlanRepositoryInterface;
use App\Domain\Repositories\TenantRepositoryInterface;
use Livewire\Component;

class Dashboard extends Component
{
    public function render(GenerateFinancialReportUseCase $generateFinancialReport)
    {
        $tenants = app(TenantRepositoryInterface::class)->findAll();
        $plans = app(SubscriptionPlanRepositoryInterface::class)->findAll();
        $financials = $generateFinancialReport->execute();

        return view('livewire.dashboard', [
            'tenantCount' => count($tenants),
            'activeTenantCount' => count(array_filter($tenants, fn ($t) => $t->isActive())),
            'planCount' => count($plans),
            'currentMrrCents' => $financials['current_mrr_cents'],
            'activePayingTenantCount' => $financials['active_paying_tenant_count'],
        ])->layout('layouts.god');
    }
}
