<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'landlord';

    /**
     * `infrastructure_providers.config` held plaintext JSON: every tenant's
     * Pusher secret, S3 secret and BYOK OpenAI key, readable by anyone with a
     * SELECT on the landlord.
     *
     * That was already wrong, but backups (5.3) make it acute — from now on a
     * single landlord dump is one file containing all of those credentials in
     * the clear, and that file is deliberately copied off-host.
     *
     * Pairs with the `encrypted:array` cast on the model. Deploy order matters:
     * the model cannot read plaintext rows once the cast changes, so `migrate`
     * has to run with the new code, not after it starts serving.
     *
     * The key is APP_KEY. Losing it means losing every provider config — which
     * is the same exposure the app already has for every other encrypted column,
     * and a reason the backup encryption key is deliberately a *different* one
     * (see BackupEncrypter).
     */
    public function up(): void
    {
        // The column has to stop being `json` first. Ciphertext is a base64
        // string, which MySQL rejects outright for a JSON column — while SQLite
        // stores it as text and accepts it happily. Exactly the class of bug
        // the SQLite test suite cannot see (see CLAUDE.md): without this line
        // every provider write would have been green in the suite and broken
        // in production.
        Schema::connection('landlord')->table('infrastructure_providers', function (Blueprint $table) {
            $table->text('config')->change();
        });

        DB::connection('landlord')->table('infrastructure_providers')
            ->orderBy('id')
            ->each(function ($row) {
                if ($row->config === null || $this->isEncrypted($row->config)) {
                    return;
                }

                DB::connection('landlord')->table('infrastructure_providers')
                    ->where('id', $row->id)
                    ->update(['config' => Crypt::encryptString($row->config)]);
            });
    }

    public function down(): void
    {
        DB::connection('landlord')->table('infrastructure_providers')
            ->orderBy('id')
            ->each(function ($row) {
                if ($row->config === null || ! $this->isEncrypted($row->config)) {
                    return;
                }

                DB::connection('landlord')->table('infrastructure_providers')
                    ->where('id', $row->id)
                    ->update(['config' => Crypt::decryptString($row->config)]);
            });

        Schema::connection('landlord')->table('infrastructure_providers', function (Blueprint $table) {
            $table->json('config')->change();
        });
    }

    /**
     * Makes both directions re-runnable: a half-finished migration must not
     * double-encrypt the rows it already converted.
     */
    private function isEncrypted(string $value): bool
    {
        try {
            Crypt::decryptString($value);

            return true;
        } catch (DecryptException) {
            return false;
        }
    }
};
