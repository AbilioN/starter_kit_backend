<?php

namespace App\Application\UseCases\GodAdmin;

use App\Application\UseCases\Tenant\SyncAgentProfilesForTenantUseCase;
use App\Domain\Repositories\TenantRepositoryInterface;
use Illuminate\Support\Facades\DB;

/**
 * Fans SyncAgentProfilesForTenantUseCase out across every tenant on a set
 * of plans — called from GodAdmin/Livewire when an AgentProfile's plan
 * assignments change, so already-provisioned tenants catch up immediately
 * instead of waiting for their next own plan change. Same
 * iterate-tenants-and-point-connection shape as
 * MigrateTenantDatabasesUseCase/SeedSystemTemplatesForAllTenantsUseCase.
 * Synchronous (runs in the GodAdmin request) — acceptable at this
 * project's scale, same call already made for `tenant:migrate`/
 * `tenant:seed-system-templates`.
 */
class SyncAgentProfilesForAllTenantsOnPlansUseCase
{
    public function __construct(
        private TenantRepositoryInterface $tenantRepository,
        private SyncAgentProfilesForTenantUseCase $syncAgentProfiles,
    ) {}

    /**
     * @param array<int, string> $planIds
     */
    public function execute(array $planIds): void
    {
        foreach (array_unique($planIds) as $planId) {
            foreach ($this->tenantRepository->findBySubscriptionPlanId($planId) as $tenant) {
                $this->pointTenantConnectionAt($tenant->databaseName);
                $this->syncAgentProfiles->execute($planId);
            }
        }
    }

    private function pointTenantConnectionAt(string $databaseName): void
    {
        if (config('database.connections.tenant.database') !== $databaseName) {
            config(['database.connections.tenant.database' => $databaseName]);
            DB::purge('tenant');
        }

        config(['database.default' => 'tenant']);
    }
}
