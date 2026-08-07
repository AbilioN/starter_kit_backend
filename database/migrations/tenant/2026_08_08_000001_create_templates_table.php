<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::create('templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            // text_email | sms | html_email | pdf | ai_prompt
            $table->string('type');
            // text | html | positions — explicit, never sniffed from body content
            // (positions = JSON array of TemplateEntry, only used by pdf-with-background)
            $table->string('body_format');
            $table->longText('body')->nullable();
            $table->string('subject')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            // sender (from-address override), locked (bool) — see docs, more
            // fields deferred until a real MergeContext entity exists
            $table->json('options')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('templates');
    }
};
