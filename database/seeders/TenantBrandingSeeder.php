<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * Landlord seeder, not tenant - Tenant doesn't exist inside DatabaseSeeder's
 * tenant-scoped run() chain. Invoke directly:
 * php artisan db:seed --class=TenantBrandingSeeder
 *
 * Applies the default logo/color palette from
 * storage/app/public/tenant-logos/tenant-branding.json to the demo tenants
 * used throughout local dev (tenant-a, tenant-b - see
 * docs/04-local-dev-tenants.md). The logo SVGs already live at
 * storage/app/public/tenant-logos/logo-tenant-{a,b}.svg; this seeder only
 * points each tenant's logo_path/theme colors at them.
 *
 * Silently skips a subdomain that hasn't been provisioned yet (tenants are
 * created via `tenant:provision`, never via this seeder) rather than
 * failing - safe to run on any environment regardless of which demo
 * tenants happen to exist.
 *
 * Both demo tenants also get `broadcasting_provider_id = null` on purpose:
 * they share the one Pusher app configured in .env, which is the intended
 * shape for entry-level plans - those tenants compete for the same WebSocket
 * bus, and that is exactly what they are paying for. A tenant only gets a
 * dedicated bus once its plan (or the tenant itself) points at its own
 * `infrastructure_providers` row, which is what higher tiers are for.
 *
 * Nulling it here is deliberate rather than incidental: leaving the column
 * untouched would let a provider assigned during manual testing survive a
 * re-seed, so the demo environment would silently stop reproducing the
 * shared-bus setup this seeder is supposed to guarantee. See
 * TenantInfrastructureResolver, which falls back tenant -> plan -> .env.
 */
class TenantBrandingSeeder extends Seeder
{
    private const DEFAULTS = [
        'tenant-a' => [
            'theme_primary_color' => '#185FA5',
            'theme_secondary_color' => '#0F6E56',
            'logo_path' => 'tenant-logos/logo-tenant-a.svg',
            'broadcasting_provider_id' => null,
        ],
        'tenant-b' => [
            'theme_primary_color' => '#D85A30',
            'theme_secondary_color' => '#993C1D',
            'logo_path' => 'tenant-logos/logo-tenant-b.svg',
            'broadcasting_provider_id' => null,
        ],
    ];

    public function run(): void
    {
        foreach (self::DEFAULTS as $subdomain => $branding) {
            $tenant = Tenant::where('subdomain', $subdomain)->first();

            if (! $tenant) {
                $this->command?->warn("Skipping '{$subdomain}' branding: tenant not provisioned yet.");

                continue;
            }

            $tenant->update($branding);

            $this->command?->info("Applied default branding to '{$subdomain}'.");
        }
    }
}
