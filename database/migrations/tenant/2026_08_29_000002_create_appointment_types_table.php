<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What KIND of thing is in the diary — meeting, call-back, follow-up,
        // task. A table rather than an enum because the answer is different in
        // every vertical, and a vertical must not need a migration to add one.
        Schema::create('appointment_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug', 64)->unique();
            $table->string('label');

            // Drawn on the card. Types are how a person reads a dense grid at a
            // glance, so the colour belongs to the type, not to each row.
            $table->string('color', 7)->default('#6B7280');
            $table->string('icon', 64)->nullable();

            // Whether this kind occupies a slot. A call-back is a reminder, not
            // an hour of someone's day, and capacity counts must be able to
            // tell the two apart.
            $table->boolean('blocks_time')->default(true);

            $table->unsignedSmallInteger('default_duration_minutes')->default(60);
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_types');
    }
};
