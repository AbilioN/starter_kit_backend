<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Documents a tenant publishes FOR its users — a manual, an FAQ, terms.
        // Per tenant, because their content is the tenant's own.
        Schema::create('agent_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->string('description')->nullable();

            // Where the PDF itself lives. The agent never reads this file: it
            // reads `content` below.
            $table->string('file_path')->nullable();

            // The text the agent searches, extracted once when the document is
            // ingested. Keeping it here rather than parsing the PDF per query
            // is what makes a lookup a database read instead of a file parse —
            // and it is the seam where real extraction (or embeddings) can be
            // added later without any tool changing.
            $table->longText('content');

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_documents');
    }
};
