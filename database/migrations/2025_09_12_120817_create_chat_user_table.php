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
        Schema::create('chat_user', function (Blueprint $table) {
            $table->id(); // bigint auto-increment — uuid not used here because BelongsToMany::attach() does not supply a pivot id
            $table->foreignUuid('chat_id')->constrained('chats')->cascadeOnDelete();
            $table->string('user_id', 36); // polymorphic - user/admin/assistant, no FK constraint
            $table->enum('user_type', ['user', 'admin', 'assistant']);
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamp('last_read_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['chat_id', 'user_id']);
            $table->index(['user_id', 'user_type']);
            $table->index(['chat_id', 'is_active']);
            $table->index(['user_id', 'is_active']);
            $table->index('last_read_at');
            $table->unique(['chat_id', 'user_id', 'user_type']);
       
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_user');
    }
};
