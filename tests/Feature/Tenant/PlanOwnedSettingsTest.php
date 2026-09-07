<?php

namespace Tests\Feature\Tenant;

use App\Models\Setting;
use Database\Seeders\SettingSeeder;
use Illuminate\Support\Facades\Artisan;
use Tests\TenantTestCase;

/**
 * The subscription plan owns its feature flags; the seeder does not.
 *
 * SettingSeeder lists `features.*` with hardcoded defaults and wrote them with
 * updateOrCreate, so re-running it reset every tenant's plan-derived flags to
 * this file's opinion. On 2026-09-06 three of four tenants here had a setting
 * contradicting their plan — including two PAYING tenants whose AI agent was
 * silently switched off, which a feature gate reads exactly as it reads a
 * deliberate choice.
 */
class PlanOwnedSettingsTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsTenant('planowned');
    }

    public function test_reseeding_does_not_overwrite_a_plan_derived_flag(): void
    {
        Artisan::call('db:seed', ['--class' => SettingSeeder::class, '--force' => true]);

        // What ChangeTenantSubscriptionPlanUseCase writes when a tenant moves
        // onto a plan that includes the AI agent.
        Setting::where('key', 'features.ai_agent')->update(['value' => '1']);

        Artisan::call('db:seed', ['--class' => SettingSeeder::class, '--force' => true]);

        $this->assertSame(
            '1',
            Setting::where('key', 'features.ai_agent')->value('value'),
            'Re-seeding must not switch off a feature the tenant pays for.',
        );
    }

    public function test_reseeding_still_refreshes_the_products_own_words(): void
    {
        // The value belongs to the plan; the label and description are the
        // product's and may improve.
        Artisan::call('db:seed', ['--class' => SettingSeeder::class, '--force' => true]);

        Setting::where('key', 'features.ai_agent')->update([
            'value' => '1',
            'label' => 'Stale label',
        ]);

        Artisan::call('db:seed', ['--class' => SettingSeeder::class, '--force' => true]);

        $row = Setting::where('key', 'features.ai_agent')->first();

        $this->assertSame('1', $row->value);
        $this->assertNotSame('Stale label', $row->label);
    }

    public function test_a_setting_the_plan_does_not_own_is_still_reset(): void
    {
        // The guard is scoped. A non-plan key stays the seeder's to define,
        // which is what keeps it useful as a way to correct a bad default.
        Artisan::call('db:seed', ['--class' => SettingSeeder::class, '--force' => true]);

        Setting::where('key', 'storage.default_disk')->update(['value' => 'nonsense']);

        Artisan::call('db:seed', ['--class' => SettingSeeder::class, '--force' => true]);

        $this->assertSame('local', Setting::where('key', 'storage.default_disk')->value('value'));
    }
}
