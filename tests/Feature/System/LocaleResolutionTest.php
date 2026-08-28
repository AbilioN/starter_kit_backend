<?php

namespace Tests\Feature\System;

use App\Models\Admin;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Tests\TenantTestCase;

/**
 * Roadmap 5.8. The panel has shipped pt/en/es/fr since Sprint 0 while the API
 * answered only in English, so a Portuguese admin read Portuguese labels with
 * English validation errors underneath them.
 *
 * Expected strings are pulled from the translation files rather than written
 * out here: what this must prove is that the right locale was *selected*, which
 * is this code's job — not that a translation package spells a sentence a
 * particular way, which is not.
 */
class LocaleResolutionTest extends TenantTestCase
{
    /**
     * Not in setUp(): one of these tests is specifically about a request with
     * no tenant at all, and a tenant bound in setUp cannot be unbound.
     */
    private function tenantSpeaking(array $enabled, string $default): void
    {
        $this->actingAsTenant();

        Setting::query()->whereIn('key', ['locales.enabled', 'locales.default'])->delete();

        // Settings are read through a per-tenant Redis cache. Writing the rows
        // straight through the model skips the repository's own invalidation.
        Cache::flush();

        Setting::create([
            'key' => 'locales.enabled', 'value' => json_encode($enabled),
            'type' => 'array', 'group' => 'general', 'label' => 'Enabled Languages', 'is_public' => true,
        ]);

        Setting::create([
            'key' => 'locales.default', 'value' => $default,
            'type' => 'string', 'group' => 'general', 'label' => 'Default Language', 'is_public' => true,
        ]);
    }

    /**
     * The one that made this worth building: a validation error, in the
     * language the tenant runs in.
     */
    public function test_validation_errors_come_back_in_the_tenants_language(): void
    {
        $this->tenantSpeaking(['en', 'pt'], 'pt');

        $response = $this->postJson('/api/register', ['email' => 'not-an-email']);

        $response->assertStatus(422);

        // The attribute name is translated too, which is the point of using the
        // files rather than a hand-written expectation: pt calls the field
        // "e-mail", and asserting "email" would fail for the right reason and
        // look like the wrong one.
        $this->assertSame(
            trans('validation.email', ['attribute' => trans('validation.attributes.email', [], 'pt')], 'pt'),
            $response->json('errors.email.0'),
        );
    }

    public function test_an_explicit_choice_beats_the_tenant_default(): void
    {
        $this->tenantSpeaking(['en', 'pt', 'es'], 'pt');

        $admin = Admin::factory()->create(['locale' => 'es']);

        $this->actingAs($admin, 'sanctum')->getJson('/api/admin/me')->assertOk();

        $this->assertSame('es', app()->getLocale());
    }

    /**
     * The organisation decides, not the laptop. Most browsers send en-US
     * wherever in the world they are, so consulting the header first would
     * answer a Brazilian tenant in English for half its own staff.
     */
    public function test_the_tenant_default_beats_the_browser_header(): void
    {
        $this->tenantSpeaking(['en', 'pt'], 'pt');

        $this->withHeader('Accept-Language', 'en-US,en;q=0.9')->getJson('/api/settings/public');

        $this->assertSame('pt', app()->getLocale());
    }

    /**
     * And where there is no organisation to ask, the header is the only
     * evidence there is — which is the whole of its usefulness.
     */
    public function test_the_header_decides_when_there_is_no_tenant(): void
    {
        $this->withHeader('Accept-Language', 'fr-FR,fr;q=0.9')->getJson('/api/health')->assertOk();

        $this->assertSame('fr', app()->getLocale());
    }

    public function test_a_language_this_application_does_not_ship_is_ignored(): void
    {
        $this->withHeader('Accept-Language', 'de-DE,de;q=0.9')->getJson('/api/health')->assertOk();

        $this->assertSame(config('app.locale'), app()->getLocale());
    }

    /**
     * Without somewhere to record the choice, the first step of the cascade is
     * unreachable and the whole thing collapses to "whatever the tenant says".
     */
    public function test_an_admin_can_choose_and_then_unchoose_a_language(): void
    {
        $this->tenantSpeaking(['en', 'pt'], 'pt');

        $admin = Admin::factory()->create(['locale' => null]);

        $this->actingAs($admin, 'sanctum')
            ->patchJson('/api/admin/me', ['locale' => 'fr'])
            ->assertOk()
            ->assertJsonPath('data.locale', 'fr');

        // Clearing it is not the same as choosing the tenant's language: it
        // means "follow the tenant", including after the tenant changes it.
        $this->actingAs($admin, 'sanctum')
            ->patchJson('/api/admin/me', ['locale' => null])
            ->assertOk()
            ->assertJsonPath('data.locale', null);

        $this->assertNull($admin->fresh()->locale);
    }

    public function test_a_language_the_product_does_not_ship_is_rejected(): void
    {
        $this->tenantSpeaking(['en'], 'en');

        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'sanctum')
            ->patchJson('/api/admin/me', ['locale' => 'de'])
            ->assertStatus(422);
    }

    /**
     * The half that is easy to forget: a notification e-mail is rendered by a
     * job, long after the request that asked for it has gone. Without the
     * locale in the payload the queue always renders in the app default, so a
     * tenant running in Portuguese sends English mail whatever anyone chose.
     */
    public function test_the_locale_travels_into_the_queue_payload(): void
    {
        config(['queue.default' => 'redis']);
        app()->setLocale('pt');

        // Dispatched by ordinary means on purpose: the payload hook is global,
        // and what is worth proving is that a job picks it up without opting in.
        dispatch((new \App\Jobs\RetrySettingsSyncJob('tenant-id', 'plan-id'))->onQueue('locale-test'));

        $payload = json_decode((string) Redis::lpop('queues:locale-test'), true);

        $this->assertSame('pt', $payload['locale'] ?? null);
    }
}
