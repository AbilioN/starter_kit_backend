<?php

namespace App\Application\UseCases\Tenant;

use App\Domain\Repositories\TenantRepositoryInterface;
use DomainException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Runs the tenant migration set (database/migrations/tenant) against one or
 * every tenant database. New tenant migrations only ever apply to the
 * database that existed when `tenant:provision` ran a given tenant into
 * being — anything added after that (like the templates table) needs an
 * explicit re-run across all of them, which is what this exists for.
 *
 * One tenant's migration failing (bad connection, drift, whatever) doesn't
 * abort the run for the rest — each tenant is isolated so an operator can
 * fix one and re-run with --tenant instead of the whole batch stalling on it.
 */
class MigrateTenantDatabasesUseCase
{
    public function __construct(
        private TenantRepositoryInterface $tenantRepository,
    ) {}

    /**
     * @return array<int, array{subdomain: string, database: string, status: 'ok'|'failed', output: string, error?: string}>
     */
    public function execute(?string $subdomain = null, bool $pretend = false): array
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

                Artisan::call('migrate', [
                    '--database' => 'tenant',
                    '--path' => 'database/migrations/tenant',
                    '--force' => true,
                    '--pretend' => $pretend,
                ]);

                $results[] = [
                    'subdomain' => $tenant->subdomain,
                    'database' => $tenant->databaseName,
                    'status' => 'ok',
                    'output' => Artisan::output(),
                ];
            } catch (Throwable $e) {
                $results[] = [
                    'subdomain' => $tenant->subdomain,
                    'database' => $tenant->databaseName,
                    'status' => 'failed',
                    'output' => Artisan::output(),
                    'error' => $e->getMessage(),
                ];
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
