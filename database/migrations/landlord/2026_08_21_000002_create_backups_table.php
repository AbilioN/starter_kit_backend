<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'landlord';

    /**
     * The backup ledger. One row per *attempt*, written as `running` before the
     * dump starts and updated at the end — a process killed mid-dump otherwise
     * leaves no trace whatsoever, which is exactly the case an operator needs to
     * see.
     *
     * It lives in the landlord and not in each tenant's database on purpose:
     * the ledger has to be readable when the tenant's own database is the thing
     * that is broken, and capacity has to be summed across tenants without
     * opening 200 connections.
     */
    public function up(): void
    {
        Schema::connection('landlord')->create('backups', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Null = the landlord's own backup. The landlord is backed up
            // regardless of any plan: without it, every surviving tenant dump
            // is an anonymous file with no map from subdomain to database_name.
            $table->foreignUuid('tenant_id')->nullable()
                ->constrained('tenants')->nullOnDelete();

            $table->string('kind');   // database|files
            $table->string('status'); // running|ok|failed|pruned

            // Which destination it went to, kept for the restore path: a tenant
            // may be moved to a different provider after a backup was written,
            // and the old copy still has to be readable from where it actually is.
            $table->foreignUuid('provider_id')->nullable()
                ->constrained('infrastructure_providers')->nullOnDelete();
            $table->string('disk_name')->nullable();

            $table->string('destination_path')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('checksum')->nullable();
            $table->boolean('is_encrypted')->default(false);

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('pruned_at')->nullable();
            $table->text('error')->nullable();

            // 5.1 observability contract: the same id that ties an HTTP request
            // to its Horizon job ties a scheduled run to its log lines.
            $table->string('request_id')->nullable();

            $table->timestamps();

            // "Latest successful backup for this tenant" is the single hottest
            // query here — the staleness check, the capacity sum and the
            // restore catalog all start from it.
            $table->index(['tenant_id', 'kind', 'status', 'finished_at']);
        });
    }

    public function down(): void
    {
        Schema::connection('landlord')->dropIfExists('backups');
    }
};
