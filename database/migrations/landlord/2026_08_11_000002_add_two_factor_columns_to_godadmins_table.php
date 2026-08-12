<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TOTP (Google Authenticator) for GodAdmin — the highest blast-radius account
 * in the system: absolute, ungated privilege over every tenant and the landlord
 * database, protected until now by email + password alone.
 *
 * `two_factor_secret` and `two_factor_recovery_codes` are cast as `encrypted`
 * on the model, so they are unreadable in a database dump. They are TEXT rather
 * than string because ciphertext is far longer than the plaintext it wraps.
 *
 * Enablement is keyed off `two_factor_confirmed_at`, not off the secret being
 * present: setting up 2FA writes a secret first, and only proving one valid
 * code turns enforcement on. Otherwise an interrupted setup would lock the
 * account out with a secret nobody ever scanned.
 */
return new class extends Migration
{
    protected $connection = 'landlord';

    public function up(): void
    {
        Schema::connection('landlord')->table('godadmins', function (Blueprint $table) {
            $table->text('two_factor_secret')->nullable()->after('password');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
            // Replay protection: the TOTP timestamp (30s slice) a code was last
            // accepted for. A code stays valid for its whole window, so without
            // this an intercepted code could be replayed inside that window.
            $table->unsignedBigInteger('two_factor_last_used_timestamp')->nullable()->after('two_factor_confirmed_at');
        });
    }

    public function down(): void
    {
        Schema::connection('landlord')->table('godadmins', function (Blueprint $table) {
            $table->dropColumn([
                'two_factor_secret',
                'two_factor_recovery_codes',
                'two_factor_confirmed_at',
                'two_factor_last_used_timestamp',
            ]);
        });
    }
};
