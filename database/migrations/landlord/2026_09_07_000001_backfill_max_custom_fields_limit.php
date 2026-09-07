<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Gives the existing subscription plans the custom-field ceiling.
 *
 * `limits.max_custom_fields` has been READ since custom fields shipped —
 * CreateFieldDefinitionUseCase enforces it through EnforcePlanLimitUseCase and
 * the configuration screen draws a budget meter from it — and written by no
 * plan, so every tier was effectively uncapped and the meter had nothing to
 * show.
 *
 * SubscriptionPlanSeeder now seeds it, but that seeder is deliberately
 * `firstOrCreate` by slug — a plan is a commercial object and re-running a
 * seeder must never rewrite one somebody has customised. So new installs get
 * it from the seeder and existing ones need this.
 *
 * Only fills the key when it is ABSENT. A plan already carrying an explicit
 * null means "unlimited" and that is a decision, not a gap.
 */
return new class extends Migration
{
    protected $connection = 'landlord';

    /** Matches SubscriptionPlanSeeder. Anything else is left uncapped. */
    private const CEILINGS = [
        'free' => 10,
        'pro' => 40,
        'enterprise' => null,
    ];

    public function up(): void
    {
        $plans = DB::connection('landlord')->table('subscription_plans')->get(['id', 'slug', 'limits']);

        foreach ($plans as $plan) {
            $limits = json_decode((string) $plan->limits, true) ?: [];

            if (array_key_exists('max_custom_fields', $limits)) {
                continue;
            }

            if (! array_key_exists($plan->slug, self::CEILINGS)) {
                continue;
            }

            $limits['max_custom_fields'] = self::CEILINGS[$plan->slug];

            DB::connection('landlord')->table('subscription_plans')
                ->where('id', $plan->id)
                ->update(['limits' => json_encode($limits), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        $plans = DB::connection('landlord')->table('subscription_plans')->get(['id', 'limits']);

        foreach ($plans as $plan) {
            $limits = json_decode((string) $plan->limits, true) ?: [];

            unset($limits['max_custom_fields']);

            DB::connection('landlord')->table('subscription_plans')
                ->where('id', $plan->id)
                ->update(['limits' => json_encode($limits)]);
        }
    }
};
