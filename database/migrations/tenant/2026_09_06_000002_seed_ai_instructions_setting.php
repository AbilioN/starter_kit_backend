<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The tenant's own standing instructions for its assistant.
 *
 * "We are an events venue. The small hall seats 40. Never quote a price."
 * `ProcessOpenAIRequest` layers this after the operator's persona and before
 * the tool block — it used to be `??` between the two, and since every seeded
 * agent profile carries a prompt, a tenant's own words were never read.
 *
 * **Deliberately NOT added to `SettingSeeder`.** That seeder writes with
 * `updateOrCreate`, so re-running it — which an operator may reasonably do to
 * pick up a new key — would silently reset every tenant's instructions to the
 * empty default. Nothing else in that file is text a customer authored, so the
 * hazard did not exist before this row. A migration reaches existing tenants
 * and new ones alike (`ProvisionTenantUseCase` migrates before it seeds), and
 * `firstOrCreate` semantics here mean re-running is harmless.
 *
 * The cap lives in the request that writes it, not here: a column limit would
 * truncate silently, and this product's rule is that caps refuse rather than
 * truncate.
 */
return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        $settings = DB::connection('tenant')->table('settings');

        if ($settings->where('key', 'ai.instructions')->exists()) {
            return;
        }

        $settings->insert([
            'id' => (string) Str::uuid(),
            'key' => 'ai.instructions',
            // Empty string, NOT null. `settings.value` is nullable but
            // Setting::toEntity() feeds it to a `string` constructor argument,
            // so one null row 500s the entire settings endpoint. Every write
            // through SettingRepository stringifies, so a direct insert like
            // this one is the only way to produce it — found by doing exactly
            // that.
            'value' => '',
            'type' => 'string',
            'group' => 'ai',
            'label' => 'Assistant instructions',
            'description' => 'Standing instructions your AI assistant follows in every conversation — '
                .'what your business is, what it must never say, the rules it should apply. '
                .'For anything longer than a paragraph or two, add a document instead.',
            // Never public: it is the tenant's own operating guidance, and the
            // public settings endpoint needs no authentication at all.
            'is_public' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::connection('tenant')->table('settings')->where('key', 'ai.instructions')->delete();
    }
};
