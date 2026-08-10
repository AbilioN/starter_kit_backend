<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'landlord';

    public function up(): void
    {
        Schema::connection('landlord')->create('agent_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('avatar')->nullable();
            // Read live by ProcessOpenAIRequest at send time, never copied
            // into a tenant's own `assistants` row — editing these here
            // takes effect immediately for every tenant that already has
            // this profile active, no propagation needed.
            $table->text('system_prompt')->nullable();
            $table->string('model')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('landlord')->dropIfExists('agent_profiles');
    }
};
