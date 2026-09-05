<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `trigger` -> `triggered_by` on the reconcile ledger.
 *
 * TRIGGER is a MySQL reserved word. Eloquent quotes identifiers, so the
 * application never noticed — but this table exists to be READ BY A HUMAN
 * during an incident, and the first thing that human types is
 *
 *     select host, trigger, status from custom_field_reconcile_runs;
 *
 * which answers with a syntax error pointing at nothing useful. A ledger that
 * is awkward to query at 2am is a ledger that does not get queried.
 *
 * A separate migration rather than an edit to 2026_09_04_000001 because that
 * one has already run against five tenants, and rewriting an applied
 * migration leaves those databases and the file permanently disagreeing —
 * and would break a FRESH tenant outright, which would create the column
 * already named `triggered_by` and then arrive here to rename a `trigger`
 * that does not exist.
 *
 * So 000001 keeps creating `trigger` and this renames it. Every tenant, new
 * or existing, reaches the same shape by the same path.
 */
return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::table('custom_field_reconcile_runs', function (Blueprint $table) {
            $table->renameColumn('trigger', 'triggered_by');
        });
    }

    public function down(): void
    {
        Schema::table('custom_field_reconcile_runs', function (Blueprint $table) {
            $table->renameColumn('triggered_by', 'trigger');
        });
    }
};
