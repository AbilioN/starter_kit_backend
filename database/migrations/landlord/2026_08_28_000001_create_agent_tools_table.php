<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'landlord';

    public function up(): void
    {
        // The catalogue is operator-curated, like agent_profiles, so it lives
        // on the landlord. What a tool *can do* lives in PHP; this table only
        // decides which handlers are exposed and how they are described.
        Schema::connection('landlord')->create('agent_tools', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // The function name the model sees. OpenAI constrains this shape,
            // and it is the key the registry is looked up by.
            $table->string('name', 64)->unique();

            // FQCN, but resolved THROUGH the registry — never instantiated from
            // this column. A row naming an unregistered class is inactive.
            $table->string('handler');

            // Operator-editable, and the single most load-bearing field here:
            // it is what steers the model towards the right tool.
            $table->text('description');

            // Optional narrowing of the handler's own JSON Schema. Never
            // widening — the handler's schema is the outer bound.
            $table->json('parameters_override')->nullable();

            $table->unsignedSmallInteger('max_rows')->default(50);
            $table->boolean('is_active')->default(true);

            // DISPLAY ONLY. AgentToolInterface::isMutating() is authoritative;
            // if this column were trusted, flipping one row would grant an
            // agent write access.
            $table->boolean('is_mutating')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('landlord')->dropIfExists('agent_tools');
    }
};
