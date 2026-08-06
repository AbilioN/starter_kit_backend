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
 */
class TenantBrandingSeeder extends Seeder
{
    private const DEFAULTS = [
        'tenant-a' => [
            'theme_primary_color' => '#185FA5',
            'theme_secondary_color' => '#0F6E56',
            'logo_path' => 'tenant-logos/logo-tenant-a.svg',
        ],
        'tenant-b' => [
            'theme_primary_color' => '#D85A30',
            'theme_secondary_color' => '#993C1D',
            'logo_path' => 'tenant-logos/logo-tenant-b.svg',
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
