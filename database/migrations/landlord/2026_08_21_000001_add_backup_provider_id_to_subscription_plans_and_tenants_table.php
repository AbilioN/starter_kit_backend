<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'landlord';

    public function up(): void
    {
        // Fourth provider type, same chain as broadcasting/storage/ai:
        // tenant override wins, plan default second.
        //
        // The one thing that must NOT be copied from the other three: for them,
        // "nothing resolved" harmlessly means "use the global .env" and the
        // product keeps working. Here the same reflex silently stops backing a
        // tenant up, which is the worst failure this feature can have. The
        // chain ends at the BACKUP_* disk from .env, and no destination at all
        // is a recorded failure — see TenantInfrastructureResolver::resolveBackupConfig()
        // and RunDatabaseBackupUseCase.
        Schema::connection('landlord')->table('subscription_plans', function (Blueprint $table) {
            $table->foreignUuid('backup_provider_id')->nullable()->after('ai_provider_id')
                ->constrained('infrastructure_providers')->nullOnDelete();
        });

        Schema::connection('landlord')->table('tenants', function (Blueprint $table) {
            $table->foreignUuid('backup_provider_id')->nullable()->after('ai_provider_id')
                ->constrained('infrastructure_providers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection('landlord')->table('subscription_plans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('backup_provider_id');
        });

        Schema::connection('landlord')->table('tenants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('backup_provider_id');
        });
    }
};
