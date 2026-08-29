<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Appointments get their OWN table, with a real duration and a type
        // discriminator.
        //
        // This is the one thing the MADCRM study says to change rather than
        // copy. There, an appointment is a set of columns on the client row —
        // meeting_date, meet2_date, meet3_date — which caps a client at three
        // appointments, forces a UNION-per-column loader to read the diary at
        // all, and makes "when does this end" unanswerable. Everything else in
        // that design survives this change untouched; the loader still returns
        // one flat list, and it gets simpler for it.
        Schema::create('appointments', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // What the appointment is ABOUT, polymorphic and nullable. The
            // starter kit has no "client" — every vertical brings its own noun.
            // A morph lets a business point this at whatever that is without
            // this table learning the word.
            $table->string('subject_type')->nullable();
            $table->string('subject_id', 36)->nullable();

            $table->foreignUuid('appointment_type_id')->constrained('appointment_types');
            $table->foreignUuid('appointment_status_id')->constrained('appointment_statuses');

            $table->string('title');
            $table->text('description')->nullable();

            // A real interval. Multi-day is just an ends_at on another day, so
            // nothing has to expand a row into one-per-day to draw a bar.
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->boolean('all_day')->default(false);

            // Who is expected to be there. Nullable because an unassigned slot
            // is a real state — it is what a dispatcher is looking at.
            $table->string('assigned_admin_id', 36)->nullable();
            $table->string('created_by_admin_id', 36)->nullable();

            // Where. Kept ON the appointment rather than only on the subject:
            // a visit happens somewhere, and that somewhere can differ from the
            // subject's registered address. It is also what the route optimiser
            // reads, so a stop is a row here and nothing has to join to find it.
            $table->string('location_address')->nullable();
            $table->string('location_postcode', 32)->nullable();
            $table->string('location_city')->nullable();
            $table->decimal('location_lat', 10, 7)->nullable();
            $table->decimal('location_lng', 10, 7)->nullable();

            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // The agenda reads one window at a time, so the window is the index.
            $table->index(['starts_at', 'ends_at']);
            $table->index(['assigned_admin_id', 'starts_at']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
