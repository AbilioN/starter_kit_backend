<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::table('assistants', function (Blueprint $table) {
            // Cross-database reference to the landlord agent_profiles.id
            // this row was synced from — not a real FK, tenant DBs can't
            // constrain against the landlord DB. Null means a manually
            // seeded/created assistant (e.g. AssistantSeeder's demo rows);
            // SyncAgentProfilesForTenantUseCase only ever touches rows it
            // created itself, identified by this column being set.
            $table->string('agent_profile_id')->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('assistants', function (Blueprint $table) {
            $table->dropColumn('agent_profile_id');
        });
    }
};
