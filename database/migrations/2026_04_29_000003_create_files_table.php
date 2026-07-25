<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('files', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('original_name');
            $table->string('stored_name');
            $table->string('disk')->default('local'); // local|s3
            $table->string('path');
            $table->string('mime_type');
            $table->unsignedBigInteger('size'); // bytes
            $table->string('uploadable_type')->nullable();
            $table->string('uploadable_id')->nullable(); // string to support both int and UUID morph targets
            $table->index(['uploadable_type', 'uploadable_id']);
            $table->string('folder')->nullable(); // logical grouping
            $table->boolean('is_public')->default(false);
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};
