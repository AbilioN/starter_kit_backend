<?php

namespace Tests\Feature\Tenant;

use App\Application\Services\TenantLocales;
use App\Models\Setting;
use Tests\TenantTestCase;

/**
 * Which languages an organisation operates in.
 *
 * The distinction these tests protect: `available_locales` is what the PRODUCT
 * can render, `locales.enabled` is what this ORGANISATION publishes in. The
 * first bounds what may be stored; the second bounds what the interface asks
 * for. Collapsing them in either direction is the bug.
 */
class TenantLocalesTest extends TenantTestCase
{
    private TenantLocales $locales;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsTenant('locales');
        $this->locales = app(TenantLocales::class);

        config(['app.available_locales' => ['en', 'pt', 'es', 'fr']]);
    }

    private function set(string $key, mixed $value, string $type = 'string'): void
    {
        Setting::updateOrCreate(
            ['key' => $key],
            [
                'value' => is_array($value) ? json_encode($value) : (string) $value,
                'type' => $type, 'group' => 'general', 'label' => $key, 'is_public' => true,
            ],
        );
    }

    public function test_it_returns_the_languages_the_tenant_chose(): void
    {
        $this->set('locales.enabled', ['pt', 'en'], 'array');

        $this->assertSame(['pt', 'en'], $this->locales->enabled());
    }

    public function test_a_language_the_product_cannot_render_is_dropped(): void
    {
        // A setting outlives a translation directory. Without the intersection
        // the authoring UI draws a tab for a language nothing can render, and
        // every string typed into it falls back silently.
        $this->set('locales.enabled', ['pt', 'klingon'], 'array');

        $this->assertSame(['pt'], $this->locales->enabled());
    }

    public function test_it_is_never_empty(): void
    {
        // A tenant that never configured languages still runs one, and the
        // authoring UI needs a tab to draw.
        $this->set('locales.enabled', [], 'array');
        $this->set('locales.default', 'pt');

        $this->assertSame(['pt'], $this->locales->enabled());
    }

    public function test_the_default_is_one_of_the_offered_languages(): void
    {
        // A default outside the enabled set would have the UI mark a tab that
        // is never drawn.
        $this->set('locales.enabled', ['pt', 'en'], 'array');
        $this->set('locales.default', 'fr');

        $this->assertSame('pt', $this->locales->defaultAmongEnabled());
    }

    public function test_the_authoring_payload_carries_both_lists(): void
    {
        // `available` is wider on purpose: it is what a WRITE is validated
        // against, so a label authored before a locale was switched off still
        // renders and still saves.
        $this->set('locales.enabled', ['pt'], 'array');
        $this->set('locales.default', 'pt');

        $payload = $this->locales->forAuthoring();

        $this->assertSame(['pt'], $payload['enabled']);
        $this->assertSame('pt', $payload['default']);
        $this->assertContains('fr', $payload['available']);
    }

    public function test_the_bulk_endpoint_refuses_what_the_single_one_refuses(): void
    {
        // `PUT /settings` validated with its own inline rules and so walked
        // past UpdateSettingRequest entirely — a bulk write could have emptied
        // the language list or stored megabytes into a key that rides in every
        // system prompt. Both endpoints now share one rule set.
        // UpdateSettingUseCase refuses a key that does not exist, so the row
        // has to be there before the endpoint can be judged on its validation.
        $this->set('locales.enabled', ['pt'], 'array');

        $admin = \App\Models\Admin::factory()->create(['is_super_admin' => true, 'is_active' => true]);
        \Laravel\Sanctum\Sanctum::actingAs($admin);

        $this->putJson('/api/admin/settings', [
            'settings' => [['key' => 'locales.enabled', 'value' => ['pt', 'klingon']]],
        ])->assertStatus(422);

        $this->putJson('/api/admin/settings', [
            'settings' => [['key' => 'locales.enabled', 'value' => ['pt', 'en']]],
        ])->assertOk();
    }

    public function test_a_refused_bulk_write_is_422_not_500(): void
    {
        // The method wraps everything in a catch-all that answers 500, so a
        // rejected value reported itself as a server fault: useless to the
        // caller and misleading to a monitor.
        $admin = \App\Models\Admin::factory()->create(['is_super_admin' => true, 'is_active' => true]);
        \Laravel\Sanctum\Sanctum::actingAs($admin);

        $this->set('ai.instructions', '');

        $this->putJson('/api/admin/settings', [
            'settings' => [['key' => 'ai.instructions', 'value' => str_repeat('a', 5000)]],
        ])->assertStatus(422);
    }

    public function test_a_comma_separated_setting_is_understood_too(): void
    {
        // The seeded shape is a JSON array, but the settings screen writes
        // strings; tolerating both beats an empty switcher.
        $this->set('locales.enabled', 'pt, en');

        $this->assertSame(['pt', 'en'], $this->locales->enabled());
    }
}
