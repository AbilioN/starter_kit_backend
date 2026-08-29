<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Where a thing STANDS — booked, confirmed, done, cancelled, no-show.
        // Separate from type for the same reason a tenant needs its own words
        // for both, and because the counts an agenda shows are per status
        // ("12 appointments, 8 confirmed") while the colours are per type.
        Schema::create('appointment_statuses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug', 64)->unique();
            $table->string('label');
            $table->string('color', 7)->default('#6B7280');

            // The two facts the grid needs to reason about a status without
            // knowing a tenant's vocabulary: does it count as confirmed, and is
            // it an end state (so it stops appearing in "needs attention").
            $table->boolean('counts_as_confirmed')->default(false);
            $table->boolean('is_final')->default(false);

            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_statuses');
    }
};
