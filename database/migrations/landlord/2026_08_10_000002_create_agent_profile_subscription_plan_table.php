<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'landlord';

    public function up(): void
    {
        // Genuinely many-to-many (one profile can be offered on several
        // plans, one plan can offer several profiles) — unlike
        // infrastructure_providers' "one active slot per tenant/plan",
        // explicit FK columns don't fit here, a real pivot does.
        Schema::connection('landlord')->create('agent_profile_subscription_plan', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('agent_profile_id')->constrained('agent_profiles')->cascadeOnDelete();
            $table->foreignUuid('subscription_plan_id')->constrained('subscription_plans')->cascadeOnDelete();
            $table->unique(['agent_profile_id', 'subscription_plan_id'], 'agent_profile_plan_unique');
        });
    }

    public function down(): void
    {
        Schema::connection('landlord')->dropIfExists('agent_profile_subscription_plan');
    }
};
