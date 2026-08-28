<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'landlord';

    public function up(): void
    {
        // Mirrors agent_profile_subscription_plan exactly, for the same reason:
        // genuinely many-to-many, so a real pivot rather than FK columns.
        // Plan gating comes free — profiles are already assigned per plan.
        Schema::connection('landlord')->create('agent_profile_agent_tool', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('agent_profile_id')->constrained('agent_profiles')->cascadeOnDelete();
            $table->foreignUuid('agent_tool_id')->constrained('agent_tools')->cascadeOnDelete();
            $table->unique(['agent_profile_id', 'agent_tool_id'], 'agent_profile_tool_unique');
        });
    }

    public function down(): void
    {
        Schema::connection('landlord')->dropIfExists('agent_profile_agent_tool');
    }
};
