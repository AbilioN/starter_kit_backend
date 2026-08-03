<?php

namespace App\Console\Commands;

use App\Application\UseCases\Tenant\ProvisionTenantUseCase;
use DomainException;
use Illuminate\Console\Command;

class ProvisionTenantCommand extends Command
{
    protected $signature = 'tenant:provision
        {name : Display name for the tenant}
        {subdomain : Unique subdomain, e.g. "tenant-a"}
        {--plan= : Subscription plan ID to assign}
        {--admin-email=owner@example.com : Email for the first (tenant owner) admin}
        {--admin-password=password123 : Password for the first admin}
        {--admin-name= : Name for the first admin (defaults to "{name} Owner")}';

    protected $description = 'Provision a new tenant: creates its database, runs migrations, seeds roles/permissions, creates the first admin';

    public function handle(ProvisionTenantUseCase $provisionTenant): int
    {
        try {
            $tenant = $provisionTenant->execute(
                name: $this->argument('name'),
                subdomain: $this->argument('subdomain'),
                subscriptionPlanId: $this->option('plan'),
                createdVia: 'godadmin',
                adminEmail: $this->option('admin-email'),
                adminPassword: $this->option('admin-password'),
                adminName: $this->option('admin-name'),
            );
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Tenant '{$tenant->name}' provisioned: subdomain={$tenant->subdomain}, database={$tenant->databaseName}");

        return self::SUCCESS;
    }
}
