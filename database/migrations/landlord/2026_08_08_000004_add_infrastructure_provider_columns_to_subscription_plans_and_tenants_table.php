<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'landlord';

    public function up(): void
    {
        // Plan-level default. Nullable — a plan with neither set keeps
        // using the global .env-configured Pusher app / S3 bucket exactly
        // as today (TenantInfrastructureResolver returns null and nothing
        // touches config()).
        Schema::connection('landlord')->table('subscription_plans', function (Blueprint $table) {
            $table->foreignUuid('broadcasting_provider_id')->nullable()->after('icon_paths')
                ->constrained('infrastructure_providers')->nullOnDelete();
            $table->foreignUuid('storage_provider_id')->nullable()->after('broadcasting_provider_id')
                ->constrained('infrastructure_providers')->nullOnDelete();
        });

        // Tenant-level override — wins over the plan's default when set.
        Schema::connection('landlord')->table('tenants', function (Blueprint $table) {
            $table->foreignUuid('broadcasting_provider_id')->nullable()->after('logo_path')
                ->constrained('infrastructure_providers')->nullOnDelete();
            $table->foreignUuid('storage_provider_id')->nullable()->after('broadcasting_provider_id')
                ->constrained('infrastructure_providers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection('landlord')->table('subscription_plans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('broadcasting_provider_id');
            $table->dropConstrainedForeignId('storage_provider_id');
        });

        Schema::connection('landlord')->table('tenants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('broadcasting_provider_id');
            $table->dropConstrainedForeignId('storage_provider_id');
        });
    }
};
