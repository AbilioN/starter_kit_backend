<?php

namespace App\Application\UseCases\GodAdmin;

use App\Models\MockPayment;
use App\Models\Tenant;
use Carbon\Carbon;

class GenerateFinancialReportUseCase
{
    public function execute(): array
    {
        $activeTenantsWithPlan = Tenant::query()
            ->join('subscription_plans', 'tenants.subscription_plan_id', '=', 'subscription_plans.id')
            ->where('tenants.status', 'active');

        $currentMrrCents = (clone $activeTenantsWithPlan)->sum('subscription_plans.price_cents');
        $activePayingTenantCount = (clone $activeTenantsWithPlan)->count();

        $byPlan = (clone $activeTenantsWithPlan)
            ->selectRaw('subscription_plans.id as plan_id, subscription_plans.name as plan_name, subscription_plans.is_public, COUNT(tenants.id) as tenant_count, SUM(subscription_plans.price_cents) as total_price_cents')
            ->groupBy('subscription_plans.id', 'subscription_plans.name', 'subscription_plans.is_public')
            ->orderByDesc('total_price_cents')
            ->get()
            ->map(fn ($row) => [
                'plan_id' => $row->plan_id,
                'plan_name' => $row->plan_name,
                'is_public' => (bool) $row->is_public,
                'tenant_count' => (int) $row->tenant_count,
                'total_price_cents' => (int) $row->total_price_cents,
            ])
            ->all();

        $visibilitySplit = (clone $activeTenantsWithPlan)
            ->selectRaw('subscription_plans.is_public, COUNT(tenants.id) as tenant_count, SUM(subscription_plans.price_cents) as total_price_cents')
            ->groupBy('subscription_plans.is_public')
            ->get()
            ->reduce(function (array $carry, $row) {
                $key = $row->is_public ? 'public' : 'private';
                $carry[$key] = [
                    'tenant_count' => (int) $row->tenant_count,
                    'total_price_cents' => (int) $row->total_price_cents,
                ];

                return $carry;
            }, ['public' => ['tenant_count' => 0, 'total_price_cents' => 0], 'private' => ['tenant_count' => 0, 'total_price_cents' => 0]]);

        return [
            'current_mrr_cents' => (int) $currentMrrCents,
            'active_paying_tenant_count' => $activePayingTenantCount,
            'total_lifetime_revenue_cents' => (int) MockPayment::sum('amount_cents'),
            'by_plan' => $byPlan,
            'visibility_split' => $visibilitySplit,
            'monthly_revenue' => $this->monthlyRevenueLast12Months(),
        ];
    }

    /**
     * Portable across MySQL (production) and SQLite (tests, both landlord
     * and tenant connections) - avoids driver-specific date functions in
     * favor of a whereYear()/whereMonth() loop, same approach already used
     * for the per-day breakdown in GetDashboardMetricsUseCase.
     */
    private function monthlyRevenueLast12Months(): array
    {
        $now = Carbon::now();
        $months = [];

        for ($i = 11; $i >= 0; $i--) {
            $month = $now->copy()->subMonths($i);

            $months[] = [
                'month' => $month->format('Y-m'),
                'total_cents' => (int) MockPayment::whereYear('created_at', $month->year)
                    ->whereMonth('created_at', $month->month)
                    ->sum('amount_cents'),
            ];
        }

        return $months;
    }
}
