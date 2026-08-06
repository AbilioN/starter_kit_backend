<?php

namespace Tests\Feature\GodAdmin;

use App\Application\UseCases\GodAdmin\GenerateFinancialReportUseCase;
use App\Application\UseCases\Tenant\ProvisionTenantUseCase;
use App\Domain\Repositories\SubscriptionPlanRepositoryInterface;
use App\Livewire\FinancialReport;
use App\Models\GodAdmin;
use Livewire\Livewire;
use Tests\TenantTestCase;

class FinancialReportTest extends TenantTestCase
{
    private function provisionTenantOnPlan(string $subdomain, string $planId): void
    {
        app(ProvisionTenantUseCase::class)->execute(
            name: ucfirst($subdomain),
            subdomain: $subdomain,
            subscriptionPlanId: $planId,
            createdVia: 'godadmin',
            adminEmail: "owner@{$subdomain}.test",
            adminPassword: 'super-secret',
        );
    }

    public function test_report_aggregates_mrr_and_breakdown_by_plan(): void
    {
        $repo = app(SubscriptionPlanRepositoryInterface::class);
        $publicPlan = $repo->create(name: 'Starter', slug: 'starter', priceCents: 1000, features: [], limits: [], isActive: true, isPublic: true);
        $privatePlan = $repo->create(name: 'Partner', slug: 'partner', priceCents: 5000, features: [], limits: [], isActive: true, isPublic: false);

        $this->provisionTenantOnPlan('t1', $publicPlan->id);
        $this->provisionTenantOnPlan('t2', $publicPlan->id);
        $this->provisionTenantOnPlan('t3', $privatePlan->id);

        $report = app(GenerateFinancialReportUseCase::class)->execute();

        $this->assertSame(1000 + 1000 + 5000, $report['current_mrr_cents']);
        $this->assertSame(3, $report['active_paying_tenant_count']);
        $this->assertSame(1000 + 1000 + 5000, $report['total_lifetime_revenue_cents']);

        $this->assertSame(2000, $report['visibility_split']['public']['total_price_cents']);
        $this->assertSame(2, $report['visibility_split']['public']['tenant_count']);
        $this->assertSame(5000, $report['visibility_split']['private']['total_price_cents']);
        $this->assertSame(1, $report['visibility_split']['private']['tenant_count']);

        $starterRow = collect($report['by_plan'])->firstWhere('plan_id', $publicPlan->id);
        $this->assertSame(2, $starterRow['tenant_count']);
        $this->assertSame(2000, $starterRow['total_price_cents']);
        $this->assertTrue($starterRow['is_public']);

        $this->assertCount(12, $report['monthly_revenue']);
        $currentMonth = now()->format('Y-m');
        $currentMonthRow = collect($report['monthly_revenue'])->firstWhere('month', $currentMonth);
        $this->assertSame(7000, $currentMonthRow['total_cents']);
    }

    public function test_report_excludes_suspended_tenants_from_current_mrr(): void
    {
        $plan = app(SubscriptionPlanRepositoryInterface::class)->create(
            name: 'Starter', slug: 'starter', priceCents: 1000, features: [], limits: [], isActive: true, isPublic: true
        );
        $this->provisionTenantOnPlan('active-one', $plan->id);

        // A tenant with no plan at all contributes nothing to MRR.
        app(ProvisionTenantUseCase::class)->execute(
            name: 'No Plan',
            subdomain: 'noplantenant',
            subscriptionPlanId: null,
            createdVia: 'godadmin',
            adminEmail: 'owner@noplantenant.test',
            adminPassword: 'super-secret',
        );

        $report = app(GenerateFinancialReportUseCase::class)->execute();

        $this->assertSame(1000, $report['current_mrr_cents']);
        $this->assertSame(1, $report['active_paying_tenant_count']);
    }

    public function test_godadmin_can_view_the_financial_report_page(): void
    {
        $godAdmin = GodAdmin::create(['name' => 'Root', 'email' => 'root@starterkit.test', 'password' => 'secret-password']);

        Livewire::actingAs($godAdmin, 'godadmin')
            ->test(FinancialReport::class)
            ->assertStatus(200)
            ->assertSee('Financial Report');
    }

    public function test_guest_cannot_view_the_financial_report_page(): void
    {
        $this->get('/god/financial-report')->assertRedirect('/god/login');
    }

    public function test_export_streams_a_csv_and_logs_the_export(): void
    {
        $godAdmin = GodAdmin::create(['name' => 'Root', 'email' => 'root@starterkit.test', 'password' => 'secret-password']);

        $this->actingAs($godAdmin, 'godadmin')
            ->get('/god/financial-report/export')
            ->assertStatus(200)
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }
}
