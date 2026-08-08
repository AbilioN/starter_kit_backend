<?php

namespace App\Application\UseCases\GodAdmin;

use App\Application\UseCases\Tenant\ChangeTenantSubscriptionPlanUseCase;
use App\Domain\Repositories\TenantRepositoryInterface;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * GodAdmin/Livewire has no connection to any specific tenant's database by
 * default (its routes aren't wrapped by IdentifyTenant) — unlike the
 * tenant-owner-initiated flow, ChangeTenantSubscriptionPlanUseCase can't
 * just assume `tenant` is already pointed at the right place here. This
 * points it at the target tenant first (mirrors NotifyTenantOwnerUseCase),
 * runs the existing use case unchanged, then restores whatever connection
 * state was there before.
 */
class ChangeTenantSubscriptionPlanAsGodAdminUseCase
{
    public function __construct(
        private TenantRepositoryInterface $tenantRepository,
        private ChangeTenantSubscriptionPlanUseCase $changeSubscriptionPlan,
    ) {}

    public function execute(string $actorId, string $tenantId, string $subscriptionPlanId): void
    {
        $tenant = $this->tenantRepository->findById($tenantId);

        if (! $tenant) {
            throw new DomainException("Tenant {$tenantId} not found.");
        }

        $previousDatabase = config('database.connections.tenant.database');
        $previousDefault = config('database.default');
        $switched = $previousDatabase !== $tenant->databaseName;

        try {
            if ($switched) {
                config(['database.connections.tenant.database' => $tenant->databaseName]);
                DB::purge('tenant');
            }
            config(['database.default' => 'tenant']);

            $this->changeSubscriptionPlan->execute($tenantId, $actorId, $subscriptionPlanId);
        } finally {
            if ($switched) {
                config(['database.connections.tenant.database' => $previousDatabase]);
                DB::purge('tenant');
            }
            config(['database.default' => $previousDefault]);
        }
    }
}
