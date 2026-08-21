<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks a Sanctum token as belonging to a GodAdmin support session rather than
 * to the admin who owns it.
 *
 * A column rather than a convention encoded in the token's name: this is what
 * every audited write is attributed to, so it has to be queryable and it has
 * to be impossible to forge from the client side. Abilities cannot carry it
 * either — a normal admin token is created with `['*']`, for which every
 * `tokenCan()` check returns true, so an ability named "impersonation" would
 * report every ordinary session as impersonated.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->string('impersonated_by', 36)->nullable()->after('abilities');
            $table->index('impersonated_by', 'idx_pat_impersonated_by');
        });
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropIndex('idx_pat_impersonated_by');
            $table->dropColumn('impersonated_by');
        });
    }
};
