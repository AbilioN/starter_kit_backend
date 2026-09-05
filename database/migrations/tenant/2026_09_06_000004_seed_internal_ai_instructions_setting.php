<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Standing instructions that only STAFF turns see.
 *
 * The sibling key `ai.instructions` reaches everyone the assistant serves,
 * end users included. That is right for "we are an events venue in Sintra,
 * never quote a price" and dangerous for "our floor price is 800, the small
 * hall actually seats 55 but we sell it as 40" — and the panel's own label
 * ("what the assistant must never do") invites exactly the second kind.
 *
 * The same change that added this gave `agent_documents` an `internal` /
 * `published` audience for precisely that reason. Leaving instructions as one
 * undifferentiated blob piped into every turn would have contradicted it in
 * the same release.
 *
 * Same reasoning as the sibling for staying out of `SettingSeeder`: that
 * seeder writes with `updateOrCreate`, so re-running it would wipe text a
 * customer wrote.
 */
return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        $settings = DB::connection('tenant')->table('settings');

        if ($settings->where('key', 'ai.instructions_internal')->exists()) {
            return;
        }

        $settings->insert([
            'id' => (string) Str::uuid(),
            'key' => 'ai.instructions_internal',
            // Empty string, not null: Setting::toEntity() feeds this to a
            // `string` constructor argument.
            'value' => '',
            'type' => 'string',
            'group' => 'ai',
            'label' => 'Internal assistant instructions',
            'description' => 'Followed only when a member of your team is talking to the assistant. '
                .'Never shown to your end users. Put margins, floor prices and internal policy here.',
            'is_public' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::connection('tenant')->table('settings')->where('key', 'ai.instructions_internal')->delete();
    }
};
