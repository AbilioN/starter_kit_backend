<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            
            // Quem fez a ação
            $table->unsignedBigInteger('user_id');
            $table->string('user_type'); // Admin, User
            $table->string('user_name')->nullable(); // Cache do nome
            
            // O que foi feito
            $table->string('action', 50); // created, updated, deleted, viewed, login
            $table->string('model_type'); // App\Models\User
            $table->unsignedBigInteger('model_id')->nullable();
            
            // Detalhes da mudança
            $table->json('old_values')->nullable(); // Estado anterior
            $table->json('new_values')->nullable(); // Estado novo
            $table->text('description')->nullable(); // Descrição legível
            
            // Contexto
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->text('url')->nullable();
            $table->string('method', 10)->nullable(); // GET, POST, PUT, DELETE
            
            // Metadados
            $table->json('tags')->nullable(); // ['security', 'critical']
            $table->json('metadata')->nullable(); // Dados extras
            
            // Timestamps
            $table->timestamp('created_at');
            
            // Indexes para performance
            $table->index(['user_id', 'user_type'], 'idx_audit_user');
            $table->index(['model_type', 'model_id'], 'idx_audit_model');
            $table->index('action', 'idx_audit_action');
            $table->index('created_at', 'idx_audit_created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};

