<?php

namespace App\Application\UseCases\Tenant;

use App\Domain\Repositories\TenantRepositoryInterface;
use App\Domain\Services\TenantConnectionSwitcherInterface;
use DomainException;
use Throwable;

/**
 * Runs a callback once per tenant, against that tenant's own database.
 *
 * Under database-per-tenant, every periodic task is "do this N times, and know
 * which of the N failed". Without a shared shape for that, each scheduled job
 * would reinvent the iteration — and, more importantly, reinvent the failure
 * handling, which is the part that matters: one tenant with a broken database
 * must never stop the other 199 from being processed.
 *
 * Mirrors MigrateTenantDatabasesUseCase's contract deliberately (iterate,
 * report per tenant, isolate failures) so an operator reading either one
 * already knows how the other behaves.
 */
class RunForEachTenantUseCase
{
    public function __construct(
        private TenantRepositoryInterface $tenantRepository,
        private TenantConnectionSwitcherInterface $tenantConnection,
    ) {}

    /**
     * @param  callable  $callback  receives the Tenant entity; its return value
     *                              lands in the result row's `result` key
     * @return array<int, array{subdomain: string, database: string, status: 'ok'|'failed', result: mixed, error?: string}>
     */
    public function execute(callable $callback, ?string $subdomain = null): array
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
                $result = $this->tenantConnection->run(
                    $tenant->databaseName,
                    fn () => $callback($tenant),
                );

                $results[] = [
                    'subdomain' => $tenant->subdomain,
                    'database' => $tenant->databaseName,
                    'status' => 'ok',
                    'result' => $result,
                ];
            } catch (Throwable $e) {
                $results[] = [
                    'subdomain' => $tenant->subdomain,
                    'database' => $tenant->databaseName,
                    'status' => 'failed',
                    'result' => null,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }
}
