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
            $table->id();
            $table->unsignedBigInteger('chat_id');
            $table->text('content');
            $table->unsignedBigInteger('sender_id'); // Apenas o ID, sem sender_type
            $table->enum('sender_type', ['user', 'admin', 'assistant'])->default('user');
            $table->enum('message_type', ['text', 'image', 'file'])->default('text');
            $table->json('metadata')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            
            // Chaves estrangeiras
            $table->foreign('chat_id')->references('id')->on('chats')->onDelete('cascade');
            
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
