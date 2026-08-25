<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'landlord';

    public function up(): void
    {
        Schema::connection('landlord')->table('tenants', function (Blueprint $table) {
            // Completes the palette a tenant could already describe but not
            // store: storage/app/public/tenant-logos/tenant-branding.json has
            // carried a `tertiary` for every demo tenant since day one, and
            // TenantBrandingSeeder silently dropped it for want of a column.
            $table->string('theme_tertiary_color')->nullable()->after('theme_secondary_color');

            // Resized variants of the tenant logo, same shape and same
            // producer (IconResizingService) as subscription_plans.icon_paths:
            // ['small' => 'tenant-icons/{uuid}/small.png', 'medium' => ..., 'large' => ...].
            //
            // `text`, not `json`, mirroring the deliberate choice on
            // infrastructure_providers.config - and here for a second reason:
            // this column is written from a seeder as well as from the branding
            // endpoint, and a `json` column makes MySQL reject anything the
            // SQLite test connection would have accepted (see CLAUDE.md's
            // SQLite-vs-MySQL note).
            //
            // logo_path stays: it is the original upload, the only thing a
            // tenant can hand back for re-processing, and the fallback for
            // tenants provisioned before this column existed.
            $table->text('icon_paths')->nullable()->after('logo_path');
        });
    }

    public function down(): void
    {
        Schema::connection('landlord')->table('tenants', function (Blueprint $table) {
            $table->dropColumn(['theme_tertiary_color', 'icon_paths']);
        });
    }
};
