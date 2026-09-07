<?php

namespace App\Console\Commands;

use App\Application\UseCases\Tenant\ChangeTenantSubscriptionPlanUseCase;
use App\Application\UseCases\Tenant\RunForEachTenantUseCase;
use Illuminate\Support\Facades\DB;
use App\Domain\Entities\Tenant;
use App\Models\SubscriptionPlan;
use Illuminate\Console\Command;

/**
 * Re-applies each tenant's subscription plan to its own settings.
 *
 * ## Why this exists
 *
 * A tenant's `features.*` and `limits.*` settings are a MIRROR of its plan,
 * written at provisioning and again whenever the plan changes. `SettingSeeder`
 * also lists those keys, with hardcoded defaults, and wrote them with
 * `updateOrCreate` — so re-running it reset every tenant's mirror to the
 * seeder's opinion.
 *
 * On 2026-09-06 that had happened to three of four tenants here: one Free
 * tenant had file upload switched ON, and an Enterprise and a Pro tenant had
 * their AI agent switched OFF. Two of them were paying for a feature the
 * product was quietly refusing them, and nothing anywhere said so — a feature
 * gate reads `false` exactly as it reads a deliberate choice.
 *
 * The seeder no longer overwrites those keys. This repairs the tenants that
 * already drifted, and stays useful afterwards as the thing to run when a plan
 * itself is edited: `ChangeTenantSubscriptionPlanUseCase` only fires when a
 * tenant MOVES plan, so editing the Pro plan's features reaches nobody until
 * this runs.
 *
 * Read-only by default. `--apply` is the flag that writes, because a command
 * whose default is to change five tenants' feature flags is a command somebody
 * runs by accident once.
 */
class SyncTenantPlanSettings extends Command
{
    protected $signature = 'tenant:sync-plan-settings
                            {--tenant= : Only this subdomain}
                            {--apply : Write the changes; without it, only report}';

    protected $description = "Reconcile each tenant's feature and limit settings with its subscription plan";

    public function handle(
        RunForEachTenantUseCase $forEachTenant,
        ChangeTenantSubscriptionPlanUseCase $sync,
    ): int {
        $apply = (bool) $this->option('apply');
        $only = $this->option('tenant');

        $drifted = 0;
        $checked = 0;

        // The subdomain filter belongs to the use case, which throws a clear
        // message when it matches nothing — better than silently checking zero.
        $results = $forEachTenant->execute(function (Tenant $tenant) use ($sync, $apply, &$drifted, &$checked) {
            $checked++;

            if ($tenant->subscriptionPlanId === null) {
                $this->warn("  {$tenant->subdomain}: no plan assigned, skipped");

                return;
            }

            // Read BEFORE the sync, so the report names what was actually
            // wrong rather than what the plan says.
            $before = $this->mirrorOf($tenant);

            if (! $apply) {
                foreach ($before['drift'] as $line) {
                    $this->line("  <fg=yellow>{$tenant->subdomain}</>: {$line}");
                    $drifted++;
                }

                return;
            }

            $sync->syncFeaturesFromPlan($tenant->subscriptionPlanId);
            $sync->syncLimitsFromPlan($tenant->subscriptionPlanId);

            foreach ($before['drift'] as $line) {
                $this->line("  <fg=green>{$tenant->subdomain}</>: fixed — {$line}");
                $drifted++;
            }
        }, $only);

        // RunForEachTenantUseCase catches per-tenant throwables and records
        // them in the array it returns. Discarding that made a tenant whose
        // database was unreachable — or whose plan row had gone from the
        // landlord — vanish from the count while the command still printed an
        // all-clear and exited 0.
        $failed = array_values(array_filter($results, fn ($r) => ($r['status'] ?? null) !== 'ok'));

        foreach ($failed as $failure) {
            $this->error(sprintf(
                '  %s: %s',
                $failure['subdomain'] ?? '(unknown)',
                $failure['error'] ?? 'failed',
            ));
        }

        $this->newLine();

        if ($drifted === 0 && $failed === []) {
            $this->info("{$checked} tenant(s) checked, all settings match their plan.");

            return self::SUCCESS;
        }

        if ($failed !== []) {
            $this->error(count($failed).' tenant(s) could not be checked at all.');

            return self::FAILURE;
        }

        $this->info($apply
            ? "{$checked} tenant(s) checked, {$drifted} setting(s) repaired."
            : "{$checked} tenant(s) checked, {$drifted} setting(s) drifted. Re-run with --apply to fix.");

        // A non-zero exit on an unrepaired drift, so this is usable as a check
        // in CI or a deploy step without parsing its output.
        return ($drifted > 0 && ! $apply) ? self::FAILURE : self::SUCCESS;
    }

    /**
     * The value as STORED, not as cached.
     *
     * `Settings::get()` reads through a per-tenant cache, so a report built on
     * it answers about what the application last saw rather than about what is
     * in the database — and drift introduced outside the app is exactly the
     * kind this is meant to find. The repair path still goes through the use
     * cases, which bust the cache properly.
     */
    private function storedValue(string $key): ?string
    {
        $value = DB::table('settings')->where('key', $key)->value('value');

        return $value === null ? null : (string) $value;
    }

    /**
     * What the tenant's settings say versus what its plan says.
     *
     * @return array{drift: array<int, string>}
     */
    private function mirrorOf(Tenant $tenant): array
    {
        // The plan lives in the LANDLORD; the settings live in the tenant
        // database this callback is already connected to.
        $plan = SubscriptionPlan::on('landlord')->find($tenant->subscriptionPlanId);

        if ($plan === null) {
            return ['drift' => []];
        }

        $drift = [];

        foreach (($plan->features ?? []) as $key => $expected) {
            $actual = $this->storedValue("features.{$key}");

            // A MISSING row is drift, not a pass. Every feature gate reads an
            // absent setting as false, which is exactly the silent denial this
            // command exists to catch — a tenant provisioned before a flag
            // existed has no row for it and would have been reported clean.
            if ($actual === null) {
                $drift[] = sprintf(
                    'features.%s is missing, plan "%s" says %s',
                    $key,
                    $plan->name,
                    $expected ? 'on' : 'off',
                );

                continue;
            }

            // Both sides store booleans in their own way — the plan as JSON
            // true/false, the setting as the string '1'/'0' its boolean cast
            // reads back. Compare meaning, not representation.
            $expectedBool = (bool) $expected;
            $actualBool = is_string($actual)
                ? in_array(strtolower($actual), ['1', 'true'], true)
                : (bool) $actual;

            if ($expectedBool !== $actualBool) {
                $drift[] = sprintf(
                    'features.%s is %s, plan "%s" says %s',
                    $key,
                    $actualBool ? 'on' : 'off',
                    $plan->name,
                    $expectedBool ? 'on' : 'off',
                );
            }
        }

        // Limits too. Checking only features meant the ceiling this very
        // change introduced — limits.max_custom_fields — was reported as
        // matching on every tenant that did not have it.
        foreach (($plan->limits ?? []) as $key => $expected) {
            // A null limit means "no cap", and syncLimitsFromPlan deliberately
            // writes no row for it, so absence is the correct state.
            if ($expected === null) {
                continue;
            }

            $actual = $this->storedValue("limits.{$key}");

            if ($actual === null) {
                $drift[] = sprintf('limits.%s is missing, plan "%s" says %s', $key, $plan->name, $expected);

                continue;
            }

            if ((string) $actual !== (string) $expected) {
                $drift[] = sprintf(
                    'limits.%s is %s, plan "%s" says %s',
                    $key,
                    $actual,
                    $plan->name,
                    $expected,
                );
            }
        }

        return ['drift' => $drift];
    }
}
