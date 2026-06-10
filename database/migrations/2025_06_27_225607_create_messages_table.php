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
        Schema::create('messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('chat_id')->constrained('chats')->cascadeOnDelete();
            $table->text('content');
            $table->string('sender_id', 36); // polymorphic - user/admin/assistant, no FK constraint
            $table->enum('sender_type', ['user', 'admin', 'assistant'])->default('user');
            $table->enum('message_type', ['text', 'image', 'file'])->default('text');
            $table->json('metadata')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamp('edited_at')->nullable();
            $table->uuid('reply_to_id')->nullable();
            $table->foreign('reply_to_id')->references('id')->on('messages')->nullOnDelete();
            $table->timestamps();
            
            // Índices para melhor performance
            $table->index(['chat_id', 'created_at']);
            $table->index(['chat_id', 'is_read']);
            $table->index(['sender_id', 'created_at']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
