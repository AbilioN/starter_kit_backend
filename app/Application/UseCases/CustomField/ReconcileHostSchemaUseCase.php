<?php

namespace App\Application\UseCases\CustomField;

use App\Domain\CustomFields\CustomFieldHostRegistry;
use App\Domain\CustomFields\CustomFieldStates;
use App\Domain\CustomFields\FieldSchemaPlanner;
use App\Domain\CustomFields\SchemaIntent;
use App\Domain\Services\SchemaIntrospectorInterface;
use App\Domain\Services\SchemaReconcilerInterface;
use App\Infrastructure\CustomFields\CatalogueLoader;
use App\Models\CustomFieldDefinition;
use App\Models\CustomFieldReconcileRun;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Makes one host table match one tenant's field definitions.
 *
 * Idempotent by construction: it diffs the catalogue against what
 * `information_schema` actually reports, never against an assumed baseline.
 * That is what lets it double as the repair for a tenant whose schema drifted
 * — and tenant schemas here DO drift, because a migration reaches only the
 * databases that existed when someone last ran `tenant:migrate`.
 */
class ReconcileHostSchemaUseCase
{
    public function __construct(
        private CustomFieldHostRegistry $hosts,
        private CatalogueLoader $catalogues,
        private FieldSchemaPlanner $planner,
        private SchemaIntrospectorInterface $introspector,
        private SchemaReconcilerInterface $reconciler,
    ) {}

    public function execute(string $hostKey, string $trigger, ?string $actorAdminId = null): CustomFieldReconcileRun
    {
        // The ledger row is opened BEFORE anything that can throw — including
        // resolving the host — and every path between here and the catch is
        // inside the try.
        //
        // Resolving the host used to sit above this, which looked like
        // harmless argument validation and was not. On 2026-09-05 a Horizon
        // worker still holding a registry from before `users` was registered
        // threw here, twice, and the ledger recorded NOTHING: the definitions
        // sat on `pending` with no trace of two attempts anywhere an operator
        // would look. That is the same failure the backup ledger had, in its
        // mirror image — not "a failure recorded as work in progress" but a
        // failure recorded as nothing at all.
        //
        // That is a hard rule rather than a style preference. It was broken
        // once in this codebase by a single line — one assignment sitting two
        // lines above the try, whose getter threw — and 74 dead backup runs
        // read as `running` for a week. A failure must never be recorded as
        // work in progress.
        $run = CustomFieldReconcileRun::create([
            'host' => $hostKey,
            'triggered_by' => $trigger,
            'status' => CustomFieldReconcileRun::STATUS_RUNNING,
            'started_at' => now(),
            'request_id' => $this->requestId(),
            'actor_admin_id' => $actorAdminId,
        ]);

        try {
            // Inside the try, so an unknown host is a closed ledger row naming
            // the key that was asked for rather than a silent disappearance.
            $host = $this->hosts->require($hostKey);

            $this->reconciler->assertUsable();

            // The catalogue, not the definition rows. One derivation path, one
            // artefact the reconciler and every reader agree on — and it makes
            // the compiled catalogue load-bearing from its first commit rather
            // than dead code waiting for values to exist.
            CatalogueLoader::forget();
            $desired = $this->catalogues->load()->desiredSchema($hostKey);

            $snapshot = $this->introspector->snapshot($host->table());

            $intents = $this->planner->plan(
                $desired,
                $snapshot,
                $host->ceilings(),
                // Passed in rather than read inside the planner, so a plan is
                // reproducible and a test can assert the exact parked name.
                now()->format('ymd'),
            );

            // Written before execution. The difference between this and
            // `applied` is exactly what a process killed mid-ALTER leaves
            // behind for whoever looks afterwards.
            $run->update(['intents' => array_map(fn (SchemaIntent $i) => $i->toArray(), $intents)]);

            $applied = $this->reconciler->apply($host->table(), $intents);

            // States are derived from what the table looks like NOW, not from
            // what the plan intended. A reconcile that half-succeeded must
            // leave every definition describing reality.
            $this->settleStates($hostKey, $host->table(), $intents);

            $run->update([
                'status' => CustomFieldReconcileRun::STATUS_OK,
                'applied' => $applied,
                'finished_at' => now(),
            ]);

            CatalogueLoader::forget();
        } catch (Throwable $e) {
            $run->update([
                'status' => CustomFieldReconcileRun::STATUS_FAILED,
                'error' => $e->getMessage(),
                'finished_at' => now(),
            ]);

            Log::error('Custom field reconciliation failed.', [
                'host' => $hostKey,
                'run_id' => $run->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        return $run->refresh();
    }

    /**
     * Bring every definition's state in line with the table as it now is.
     *
     * A refusal is a first-class outcome, not an exception: the run itself
     * completed, and the DEFINITION is what carries the bad news, in a
     * translatable code the tenant reads on the screen they are already
     * looking at. Letting a ceiling breach or an unsupported ALTER bubble up
     * would put it in `failed_jobs` — where config/health.php:44-50 sets the
     * threshold to zero, so one tenant's misconfiguration would page everyone,
     * forever.
     *
     * @param  array<int, SchemaIntent>  $intents
     */
    private function settleStates(string $hostKey, string $table, array $intents): void
    {
        $refusals = [];

        foreach ($intents as $intent) {
            if ($intent->isRefusal()) {
                $refusals[$intent->column] = $intent;
            }
        }

        $snapshot = $this->introspector->snapshot($table);

        $definitions = CustomFieldDefinition::query()
            ->where('host', $hostKey)
            ->reconcilable()
            ->get();

        foreach ($definitions as $definition) {
            $column = $definition->column_name;

            if ($definition->state === CustomFieldStates::RETIRING) {
                if (! $snapshot->hasColumn($column)) {
                    $definition->update([
                        'state' => CustomFieldStates::RETIRED,
                        'reconciled_at' => now(),
                        'state_error_code' => null,
                        'state_error_params' => null,
                    ]);
                }

                continue;
            }

            if (isset($refusals[$column])) {
                $refusal = $refusals[$column];

                $definition->update([
                    'state' => CustomFieldStates::FAILED,
                    'state_error_code' => $refusal->reasonCode,
                    'state_error_params' => $refusal->reasonParams,
                ]);

                continue;
            }

            if ($snapshot->hasColumn($column)) {
                $definition->update([
                    'state' => CustomFieldStates::LIVE,
                    'reconciled_at' => now(),
                    'state_error_code' => null,
                    'state_error_params' => null,
                ]);

                continue;
            }

            // Was live, and the column is gone. Demoting the ROW is the one
            // schema-shaped thing detection is allowed to write: it stops the
            // catalogue naming a column that does not exist, which is what
            // keeps a hand-dropped column from taking a screen down. Bringing
            // the COLUMN back still needs a human to run the command.
            if ($definition->state === CustomFieldStates::LIVE) {
                $definition->update(['state' => CustomFieldStates::MISSING]);
            }
        }
    }

    /**
     * The observability contract from roadmap 5.1 — one id ties this run to
     * the request that asked for it and to the worker line that did it.
     */
    private function requestId(): ?string
    {
        $context = Log::sharedContext();

        return $context['request_id'] ?? null;
    }
}
