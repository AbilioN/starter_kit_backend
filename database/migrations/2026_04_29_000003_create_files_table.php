<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('files', function (Blueprint $table) {
            $table->id();
            $table->string('original_name');
            $table->string('stored_name');
            $table->string('disk')->default('local'); // local|s3
            $table->string('path');
            $table->string('mime_type');
            $table->unsignedBigInteger('size'); // bytes
            $table->nullableMorphs('uploadable'); // Admin or User
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
