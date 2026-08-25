<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

/**
 * Landlord seeder, not tenant - SubscriptionPlan lives on the `landlord`
 * connection and has no place in DatabaseSeeder's tenant-scoped run() chain.
 * Invoke directly:
 * php artisan db:seed --class=SubscriptionPlanSeeder
 *
 * Seeds the baseline plan catalog (free / pro / enterprise). Nothing else in
 * the codebase creates plans - they are otherwise created one by one
 * through the GodAdmin plan form - so a fresh landlord database starts with an
 * empty `subscription_plans` table and every tenant provisioned against it
 * gets `subscription_plan_id = null`, i.e. no feature flags and no limits at
 * all. This seeder is what makes a dev environment reproduce the real shape.
 *
 * The tiers follow the ones suggested in docs/09-backup-and-restore.md
 * (24h/168h frequency, 7/30/90 day retention, 1GB/10GB/uncapped).
 *
 * Deliberately firstOrCreate (by slug) rather than updateOrCreate: a plan is
 * an operator-editable record, and prices/limits adjusted through the GodAdmin
 * form must survive a re-seed. Re-running this only fills in tiers that are
 * missing. To reset one to its canonical values, delete it first.
 *
 * `null` means unlimited for every `limits.*` key, not zero - seedLimitsFromPlan()
 * skips creating the tenant setting entirely, and EnforcePlanLimitUseCase reads
 * a missing setting as "no cap". `backup_frequency_hours = null` is the one
 * that means "never", which is why the free tier also carries
 * `features.backup = false`: the two are read by different code paths
 * (ResolveBackupPolicyUseCase and the features flag) and disagreeing here is
 * how a tier ends up advertising a backup it never takes.
 */
class SubscriptionPlanSeeder extends Seeder
{
    private const PLANS = [
        [
            'name' => 'Free',
            'slug' => 'free',
            'price_cents' => 0,
            'tertiary_color' => '#64748B',
            'features' => [
                'chat' => true,
                'file_upload' => false,
                'notifications' => true,
                'ai_agent' => false,
                'backup' => false,
            ],
            'limits' => [
                'max_admins' => 2,
                'max_users' => 50,
                'max_storage_mb' => 512,
                'backup_frequency_hours' => null,
                'backup_retention_days' => 7,
                'backup_max_total_mb' => null,
            ],
        ],
        [
            'name' => 'Pro',
            'slug' => 'pro',
            'price_cents' => 9900,
            'tertiary_color' => '#185FA5',
            'features' => [
                'chat' => true,
                'file_upload' => true,
                'notifications' => true,
                'ai_agent' => true,
                'backup' => true,
            ],
            'limits' => [
                'max_admins' => 10,
                'max_users' => 1000,
                'max_storage_mb' => 10240,
                'backup_frequency_hours' => 24,
                'backup_retention_days' => 30,
                'backup_max_total_mb' => 10240,
            ],
        ],
        [
            'name' => 'Enterprise',
            'slug' => 'enterprise',
            'price_cents' => 49900,
            'tertiary_color' => '#0F6E56',
            'features' => [
                'chat' => true,
                'file_upload' => true,
                'notifications' => true,
                'ai_agent' => true,
                'backup' => true,
            ],
            'limits' => [
                'max_admins' => null,
                'max_users' => null,
                'max_storage_mb' => null,
                'backup_frequency_hours' => 24,
                'backup_retention_days' => 90,
                'backup_max_total_mb' => null,
            ],
        ],
    ];

    public function run(): void
    {
        foreach (self::PLANS as $plan) {
            $existing = SubscriptionPlan::where('slug', $plan['slug'])->first();

            if ($existing) {
                $this->command?->info("Plan '{$plan['slug']}' already exists - left untouched.");

                continue;
            }

            SubscriptionPlan::create($plan + [
                'is_active' => true,
                'is_public' => true,
            ]);

            $this->command?->info("Created plan '{$plan['slug']}'.");
        }
    }
}
