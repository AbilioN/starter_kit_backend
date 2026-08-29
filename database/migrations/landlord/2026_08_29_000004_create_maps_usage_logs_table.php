<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'landlord';

    public function up(): void
    {
        // The usage ledger. On the landlord because it is a billing artefact
        // that spans tenants — the row you bill or throttle on.
        //
        // A metered third-party feature in a multi-tenant SaaS needs three
        // things together: a cache, hard caps, and this. And the ledger has to
        // record the **billable quantity**, not the call count: maps APIs bill
        // per element, so one request for 20 stops is 20 units, and a ledger
        // counting requests would under-report by an order of magnitude.
        Schema::connection('landlord')->create('maps_usage_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id', 36)->nullable();
            $table->string('provider', 32);
            $table->string('operation', 32);

            // Elements, not calls.
            $table->unsignedInteger('quantity');

            $table->string('actor_id', 36)->nullable();
            $table->string('actor_type', 16)->nullable();
            $table->timestamp('created_at');

            $table->index(['tenant_id', 'created_at']);
            $table->index(['provider', 'operation']);
        });
    }

    public function down(): void
    {
        Schema::connection('landlord')->dropIfExists('maps_usage_logs');
    }
};
