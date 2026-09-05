<?php

namespace App\Console\Commands;

use App\Application\UseCases\CustomField\ReconcileHostSchemaUseCase;
use App\Application\UseCases\Tenant\RunForEachTenantUseCase;
use App\Models\CustomFieldReconcileRun;
use Database\Seeders\CustomFieldDemoSeeder;
use Illuminate\Console\Command;

/**
 * Seeds a demo custom field AND makes its column real, in one line.
 *
 * A command rather than `db:seed --class=...`, for a reason that bites
 * silently: `db:seed` from the console runs against `database.default`, which
 * outside a tenant-identified request is the landlord. Going through
 * RunForEachTenantUseCase is the house answer — the same shape
 * agenda:seed-demo uses — and it is the only one that reaches the right
 * database.
 *
 * It reconciles afterwards because a definition with no column is not
 * something anyone can be shown.
 */
class SeedDemoCustomFieldsCommand extends Command
{
    protected $signature = 'fields:seed-demo
        {--tenant= : Subdomain of one tenant; every tenant otherwise}
        {--force : Allow this outside local/staging}';

    protected $description = 'Seed a demo tenant-defined field on appointments and create its column';

    public function handle(RunForEachTenantUseCase $runForEachTenant, ReconcileHostSchemaUseCase $reconcile): int
    {
        // Demo data invents business records, and the one place that must
        // never happen by accident is a production tenant during a demo —
        // which is exactly when somebody is typing quickly.
        if (! app()->environment(['local', 'staging']) && ! $this->option('force')) {
            $this->error('Refusing to seed demo data in '.app()->environment().'. Pass --force if you meant it.');

            return self::FAILURE;
        }

        $results = $runForEachTenant->execute(function ($tenant) use ($reconcile) {
            (new CustomFieldDemoSeeder)->setCommand($this)->run();

            $run = $reconcile->execute('appointments', CustomFieldReconcileRun::TRIGGER_COMMAND);

            return count($run->applied ?? []).' statement(s) applied';
        }, $this->option('tenant'));

        $failed = 0;

        foreach ($results as $result) {
            if ($result['status'] !== 'ok') {
                $failed++;
                $this->line("  [{$result['subdomain']}] FAILED — ".($result['error'] ?? 'unknown'));

                continue;
            }

            $this->line("  [{$result['subdomain']}] {$result['result']}");
        }

        $this->newLine();
        $this->info(count($results).' tenant(s) processed, '.$failed.' failed.');

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
