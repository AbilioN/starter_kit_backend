<?php

namespace Database\Seeders;

use App\Domain\Services\TenantConnectionSwitcherInterface;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * Landlord seeder that runs SettingSeeder inside every tenant database.
 * Invoke directly:
 * php artisan db:seed --class=TenantSettingsSeeder
 *
 * `tenant:provision` runs RoleSeeder, PermissionSeeder and
 * SystemTemplateSeeder, but not SettingSeeder — so a provisioned tenant has
 * no app.name, no email.from_address, no storage.* and no locales.*. The only
 * settings rows it owns are the features and limits mirrored from its plan.
 * Every Settings::get() call therefore falls through to its hardcoded default,
 * and the Settings screen has almost nothing to show.
 *
 * That matters for languages specifically: `locales.enabled` and
 * `locales.default` are how a tenant says how many languages it runs, and a
 * setting that does not exist cannot be edited in the panel — the tenant would
 * be stuck on the platform default with no way to change it.
 *
 * Idempotent (SettingSeeder is updateOrCreate by key), so re-running fills in
 * settings added since a tenant was provisioned without touching values the
 * tenant has changed... except that updateOrCreate DOES overwrite `value`.
 * Run it to backfill a new setting; do not schedule it.
 */
class TenantSettingsSeeder extends Seeder
{
    public function __construct(
        private readonly TenantConnectionSwitcherInterface $tenantConnection,
    ) {}

    public function run(): void
    {
        foreach (Tenant::all() as $tenant) {
            try {
                $this->tenantConnection->run(
                    $tenant->database_name,
                    fn () => (new SettingSeeder)->run(),
                );

                $this->command?->info("[{$tenant->subdomain}] settings seeded.");
            } catch (\Throwable $e) {
                // One tenant with a broken database must not stop the rest —
                // same contract as RunForEachTenantUseCase.
                $this->command?->error("[{$tenant->subdomain}] FAILED: {$e->getMessage()}");
            }
        }
    }
}
