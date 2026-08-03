<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'landlord';

    public function up(): void
    {
        Schema::connection('landlord')->create('tenants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('subdomain')->unique();
            $table->string('database_name')->unique();
            $table->foreignUuid('subscription_plan_id')->nullable()
                ->constrained('subscription_plans')->nullOnDelete();
            $table->string('theme_primary_color')->nullable();
            $table->string('theme_secondary_color')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('status')->default('pending'); // pending|active|suspended
            $table->string('created_via'); // godadmin|self_service
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('landlord')->dropIfExists('tenants');
    }
};
