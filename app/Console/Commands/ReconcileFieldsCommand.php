<?php

namespace App\Console\Commands;

use App\Application\UseCases\CustomField\ReconcileHostSchemaUseCase;
use App\Application\UseCases\Tenant\RunForEachTenantUseCase;
use App\Domain\CustomFields\CustomFieldHostRegistry;
use App\Models\CustomFieldReconcileRun;
use Illuminate\Console\Command;

/**
 * Makes a tenant's schema match its field definitions.
 *
 * Deliberately NOT scheduled. A repairer that runs every night fixes the
 * symptom and nobody ever learns that a restore, a failed job or a bad deploy
 * caused the drift — the same failure the backup ledger had in a different
 * costume. Detection runs on a schedule; repair is typed by a person.
 *
 * Safe to run twice: the reconciler diffs against information_schema, so a
 * second run emits nothing at all.
 */
class ReconcileFieldsCommand extends Command
{
    protected $signature = 'fields:reconcile
        {--tenant= : Subdomain of one tenant; every tenant otherwise}
        {--host= : One host key; every registered host otherwise}
        {--pretend : Plan and report, change nothing}';

    protected $description = 'Add, index and retire tenant-defined field columns to match the definitions';

    public function handle(
        RunForEachTenantUseCase $runForEachTenant,
        CustomFieldHostRegistry $hosts,
        ReconcileHostSchemaUseCase $reconcile,
    ): int {
        $hostKeys = $this->option('host') ? [$this->option('host')] : $hosts->keys();

        $results = $runForEachTenant->execute(function ($tenant) use ($hostKeys, $reconcile) {
            $summary = [];

            foreach ($hostKeys as $hostKey) {
                if ($this->option('pretend')) {
                    // --pretend still opens no ledger row and runs no DDL. It
                    // exists so an operator can see what a reconcile WOULD do
                    // on a tenant they are nervous about, which is most of
                    // them the first time.
                    $summary[$hostKey] = $this->pretend($hostKey);

                    continue;
                }

                $run = $reconcile->execute($hostKey, CustomFieldReconcileRun::TRIGGER_COMMAND);
                $summary[$hostKey] = count($run->applied ?? []).' statement(s)';
            }

            return $summary;
        }, $this->option('tenant'));

        $failed = 0;

        foreach ($results as $result) {
            if ($result['status'] !== 'ok') {
                $failed++;
                $this->line("  [{$result['subdomain']}] FAILED — ".($result['error'] ?? 'unknown'));

                continue;
            }

            foreach ($result['result'] as $hostKey => $summary) {
                $this->line("  [{$result['subdomain']}] {$hostKey}: {$summary}");
            }
        }

        $this->newLine();
        $this->info(count($results).' tenant(s) processed, '.$failed.' failed.');

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function pretend(string $hostKey): string
    {
        $hosts = app(CustomFieldHostRegistry::class);
        $host = $hosts->require($hostKey);

        \App\Infrastructure\CustomFields\CatalogueLoader::forget();

        $intents = app(\App\Domain\CustomFields\FieldSchemaPlanner::class)->plan(
            app(\App\Infrastructure\CustomFields\CatalogueLoader::class)->load()->desiredSchema($hostKey),
            app(\App\Domain\Services\SchemaIntrospectorInterface::class)->snapshot($host->table()),
            $host->ceilings(),
            now()->format('ymd'),
        );

        if ($intents === []) {
            return 'nothing to do';
        }

        foreach ($intents as $intent) {
            $this->line('      '.json_encode($intent->toArray()));
        }

        return count($intents).' intent(s)';
    }
}
