<?php

namespace App\Application\UseCases\Tenant;

use App\Domain\Repositories\TenantRepositoryInterface;
use Database\Seeders\SystemTemplateSeeder;
use DomainException;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Backfills SystemTemplateSeeder onto one or every tenant database —
 * ProvisionTenantUseCase only runs it for NEWLY provisioned tenants, so
 * anything provisioned before the `key` column/system-template slots
 * existed needs this to catch up. Same iterate-and-isolate-failures shape
 * as MigrateTenantDatabasesUseCase, for the same reason: one tenant's
 * failure shouldn't stall the rest of the batch.
 */
class SeedSystemTemplatesForAllTenantsUseCase
{
    public function __construct(
        private TenantRepositoryInterface $tenantRepository,
    ) {}

    /**
     * @return array<int, array{subdomain: string, status: 'ok'|'failed', error?: string}>
     */
    public function execute(?string $subdomain = null): array
    {
        $tenants = $subdomain !== null
            ? array_filter([$this->tenantRepository->findBySubdomain($subdomain)])
            : $this->tenantRepository->findAll();

        if ($subdomain !== null && $tenants === []) {
            throw new DomainException("No tenant found with subdomain '{$subdomain}'.");
        }

        $results = [];

        foreach ($tenants as $tenant) {
            try {
                $this->pointTenantConnectionAt($tenant->databaseName);

                (new SystemTemplateSeeder())->run();

                $results[] = ['subdomain' => $tenant->subdomain, 'status' => 'ok'];
            } catch (Throwable $e) {
                $results[] = ['subdomain' => $tenant->subdomain, 'status' => 'failed', 'error' => $e->getMessage()];
            }
        }

        return $results;
    }

    private function pointTenantConnectionAt(string $databaseName): void
    {
        if (config('database.connections.tenant.database') !== $databaseName) {
            config(['database.connections.tenant.database' => $databaseName]);
            DB::purge('tenant');
        }
    }
}
