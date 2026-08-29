<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'landlord';

    public function up(): void
    {
        // Fifth provider type, same chain as the others: tenant override wins,
        // plan default second.
        //
        // Unlike `backup`, "nothing resolved" is a perfectly good answer here:
        // the routing feature falls back to the local optimiser, so a tenant
        // that has bought no maps key still gets its stops ordered — estimated
        // rather than driven, and labelled as such. That is what lets a freshly
        // cloned starter kit route without an account anywhere.
        Schema::connection('landlord')->table('subscription_plans', function (Blueprint $table) {
            $table->foreignUuid('maps_provider_id')->nullable()->after('backup_provider_id')
                ->constrained('infrastructure_providers')->nullOnDelete();
        });

        Schema::connection('landlord')->table('tenants', function (Blueprint $table) {
            $table->foreignUuid('maps_provider_id')->nullable()->after('backup_provider_id')
                ->constrained('infrastructure_providers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection('landlord')->table('subscription_plans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('maps_provider_id');
        });

        Schema::connection('landlord')->table('tenants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('maps_provider_id');
        });
    }
};
