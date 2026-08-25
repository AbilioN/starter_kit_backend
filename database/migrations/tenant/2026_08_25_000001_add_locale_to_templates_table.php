<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    protected $connection = 'tenant';

    /**
     * Default for rows that predate this column. Not read from config: a
     * migration must produce the same result whenever it runs, and
     * app.locale can change between the day a tenant is provisioned and the
     * day this migration reaches it.
     */
    private const BACKFILL_LOCALE = 'en';

    public function up(): void
    {
        Schema::connection('tenant')->table('templates', function (Blueprint $table) {
            // A translation is a FULL template, not a translated string: the
            // subject differs, the body differs, and for a positions-PDF the
            // entry coordinates differ too, because text that grows by 30% in
            // German no longer fits the box drawn for English.
            $table->string('locale', 10)->nullable()->after('key');

            // What makes N rows "the same template in other languages".
            // Needed because `key` only identifies SYSTEM slots — a template
            // a tenant authors itself has key = null, and would otherwise
            // have nothing tying its translations together.
            $table->uuid('translation_group_id')->nullable()->after('locale');
        });

        // Backfill before the constraints go on, or the unique indexes below
        // reject every existing row (they would all share locale = null).
        // Each existing template becomes the sole member of its own group.
        DB::connection('tenant')->table('templates')->orderBy('id')->each(function ($template) {
            DB::connection('tenant')->table('templates')
                ->where('id', $template->id)
                ->update([
                    'locale' => self::BACKFILL_LOCALE,
                    'translation_group_id' => (string) Str::uuid(),
                ]);
        });

        Schema::connection('tenant')->table('templates', function (Blueprint $table) {
            // The point of the whole change: `key` was unique on its own, so
            // one system slot could only ever have one row — one language.
            $table->dropUnique('templates_key_unique');
            $table->unique(['key', 'locale']);
            $table->unique(['translation_group_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('templates', function (Blueprint $table) {
            $table->dropUnique(['key', 'locale']);
            $table->dropUnique(['translation_group_id', 'locale']);
            $table->dropColumn(['locale', 'translation_group_id']);
            $table->unique('key');
        });
    }
};
