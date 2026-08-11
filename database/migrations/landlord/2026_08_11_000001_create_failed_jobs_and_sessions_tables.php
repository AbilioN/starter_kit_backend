<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Both of these tables are INFRASTRUCTURE, not tenant data, so they belong on
 * the landlord connection — the one database that always exists and never
 * moves.
 *
 * They existed only in database/migrations/tenant/ before this, which broke
 * both of them in the same way:
 *
 *  - `failed_jobs`: config/queue.php resolves the failed-job store against
 *    `DB_CONNECTION` (the legacy `starter_kit_backend` database), which has no
 *    such table. So when a queued job failed, Laravel's attempt to RECORD the
 *    failure threw a second exception and the original failure vanished —
 *    nothing in `queue:failed`, nothing in Horizon. Every failing job was
 *    silent. Writing them to a tenant database would not have worked either:
 *    a job can fail before (or without) any tenant connection being
 *    established, which is the very bug class EstablishTenantConnection exists
 *    to prevent.
 *
 *  - `sessions`: SESSION_DRIVER=database looks the table up on the default
 *    connection too, so /god/login returned 500. GodAdmin is a landlord-scoped
 *    actor authenticating against the landlord database, so its sessions have
 *    no business living in a tenant's. Paired with SESSION_CONNECTION=landlord
 *    in .env.
 */
return new class extends Migration
{
    protected $connection = 'landlord';

    public function up(): void
    {
        if (! Schema::connection('landlord')->hasTable('failed_jobs')) {
            Schema::connection('landlord')->create('failed_jobs', function (Blueprint $table) {
                $table->id();
                $table->string('uuid')->unique();
                $table->text('connection');
                $table->text('queue');
                $table->longText('payload');
                $table->longText('exception');
                $table->timestamp('failed_at')->useCurrent();
            });
        }

        if (! Schema::connection('landlord')->hasTable('sessions')) {
            Schema::connection('landlord')->create('sessions', function (Blueprint $table) {
                $table->string('id')->primary();
                // NOT Laravel's stock `foreignId('user_id')` (unsignedBigInteger):
                // `godadmins.id` is a uuid/char(36), so the stock column would
                // throw the moment an authenticated GodAdmin session was written.
                $table->uuid('user_id')->nullable()->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->longText('payload');
                $table->integer('last_activity')->index();
            });
        }
    }

    public function down(): void
    {
        Schema::connection('landlord')->dropIfExists('sessions');
        Schema::connection('landlord')->dropIfExists('failed_jobs');
    }
};
