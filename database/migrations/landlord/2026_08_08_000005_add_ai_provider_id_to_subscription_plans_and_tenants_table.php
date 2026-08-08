<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'landlord';

    public function up(): void
    {
        // Same BYOK pattern as broadcasting/storage: plan-level default,
        // tenant-level override wins when set. Nullable — a plan/tenant with
        // neither set falls back to the AI worker's own global .env
        // OPENAI_API_KEY (TenantInfrastructureResolver::resolveAiConfig()
        // returns null and ProcessOpenAIRequest sends no api_key/model
        // override, exactly like the broadcasting/storage null case).
        Schema::connection('landlord')->table('subscription_plans', function (Blueprint $table) {
            $table->foreignUuid('ai_provider_id')->nullable()->after('storage_provider_id')
                ->constrained('infrastructure_providers')->nullOnDelete();
        });

        Schema::connection('landlord')->table('tenants', function (Blueprint $table) {
            $table->foreignUuid('ai_provider_id')->nullable()->after('storage_provider_id')
                ->constrained('infrastructure_providers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection('landlord')->table('subscription_plans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ai_provider_id');
        });

        Schema::connection('landlord')->table('tenants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ai_provider_id');
        });
    }
};
