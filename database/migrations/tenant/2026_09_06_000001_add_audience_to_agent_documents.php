<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Who a document is for.
 *
 * `agent_documents` shipped with `is_active` and nothing else, and both
 * `SearchDocumentsTool` and `ListDocumentsTool` are registered **user-side**.
 * That was safe only because nothing could write to the table: the sole writer
 * was a seeder. The moment a tenant can upload — which is the point of this
 * part — a restaurant's supplier contracts and its public allergen list sit in
 * one bag, searchable by every customer.
 *
 * Two values, not a role matrix. `internal` is the tenant's own staff;
 * `published` is anyone their assistant serves. Finer control belongs to
 * `FieldViewer`'s deny-wins resolution when it is needed, and inventing a
 * second visibility model before then would guarantee the two disagree.
 *
 * **The default is `internal`, and that asymmetry is the point.** A document
 * somebody uploads without thinking about audience must not become public by
 * omission; publishing is the deliberate act. Existing rows are backfilled to
 * `published` because they are the seeded manual/FAQ/terms the table was
 * created for, and silently hiding them would be a behaviour change dressed as
 * a migration.
 */
return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::table('agent_documents', function (Blueprint $table) {
            // varchar, not enum: `custom_field_definitions.state` set the
            // precedent, and widening an enum later is a table rebuild.
            $table->string('audience', 20)->default('internal')->after('description');
        });

        // Everything that exists today predates the column and was written to
        // be read by end users.
        DB::connection('tenant')->table('agent_documents')->update(['audience' => 'published']);

        Schema::table('agent_documents', function (Blueprint $table) {
            // The user-side tools filter on exactly this pair.
            $table->index(['audience', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('agent_documents', function (Blueprint $table) {
            $table->dropIndex(['audience', 'is_active']);
            $table->dropColumn('audience');
        });
    }
};
