<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'landlord';

    public function up(): void
    {
        Schema::connection('landlord')->create('landlord_audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('actor_type'); // always 'godadmin' for now
            $table->uuid('actor_id');
            $table->string('action');
            $table->string('model');
            $table->uuid('model_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::connection('landlord')->dropIfExists('landlord_audit_logs');
    }
};
