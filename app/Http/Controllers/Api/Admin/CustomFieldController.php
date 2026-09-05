<?php

namespace App\Http\Controllers\Api\Admin;

use App\Application\Services\AdminFactory;
use App\Application\UseCases\Admin\Authorization\AuthorizeActionUseCase;
use App\Application\UseCases\CustomField\CreateFieldDefinitionUseCase;
use App\Domain\CustomFields\CustomFieldHostRegistry;
use App\Domain\CustomFields\CustomFieldStates;
use App\Domain\CustomFields\FieldTypeRegistry;
use App\Helpers\Settings;
use App\Http\Controllers\Controller;
use App\Http\Requests\CustomField\CreateCustomFieldRequest;
use App\Jobs\ReconcileTenantFieldSchema;
use App\Models\CustomFieldDefinition;
use App\Models\CustomFieldReconcileRun;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The tenant administrator's screen for inventing fields.
 *
 * Separate from the entity endpoints on purpose. Reading an appointment needs
 * the fields as CONTEXT — labels, controls, colours for what this reader may
 * see — and that rides along in the entity's own response so the panel makes
 * no second request. Configuring them is a different job with a different
 * permission, a different audience and a much larger payload: the full
 * definitions including the ones this reader cannot see values for, the host
 * catalogue, the type menu, the tenant's roles for the per-role matrix, and
 * the structural budget.
 *
 * Two permissions, not four. Creating a definition runs DDL against the
 * tenant's own database, and that is one privilege rather than a
 * create/update/delete triad.
 */
class CustomFieldController extends Controller
{
    public function __construct(
        private AuthorizeActionUseCase $authorize,
        private CustomFieldHostRegistry $hosts,
        private FieldTypeRegistry $types,
        private CreateFieldDefinitionUseCase $createDefinition,
    ) {}

    /**
     * Everything the configuration screen needs, in one response.
     *
     * The roles ride along deliberately. The per-role matrix needs them, and
     * without this the screen would have to call `GET /api/admin/roles` — which
     * costs the unrelated `role-read` slug from everyone allowed to define a
     * field. The same reasoning the templates editor already applies by
     * shipping locales beside the field catalogue, so tabs and variables
     * cannot disagree.
     */
    public function index(Request $request): JsonResponse
    {
        $admin = AdminFactory::createFromModel($request->user());
        $this->authorize->execute($admin, 'custom-field-read');

        $definitions = CustomFieldDefinition::with(['labels', 'roleRules'])
            ->orderBy('host')->orderBy('position')->orderBy('num')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'definitions' => $definitions->map(fn (CustomFieldDefinition $d) => $this->toArray($d))->all(),
                'hosts' => $this->hostCatalogue(),
                'types' => $this->typeCatalogue(),
                // Only what the matrix needs. The full role payload belongs to
                // the roles screen, and this one must not become a second way
                // to read it.
                'roles' => Role::where('is_active', true)
                    ->orderBy('name')
                    ->get(['id', 'slug', 'name'])
                    ->all(),
                'locales' => config('app.available_locales', []),
            ],
        ]);
    }

    /**
     * Declares a field. Answers 202, because the column is not made here.
     *
     * MySQL commits implicitly on DDL, so the definition write and its ALTER
     * cannot be atomic at any price — a transaction around both would be a
     * lie only the SQLite suite could confirm. The row is written, the field
     * is born `pending`, and the reconcile is queued.
     */
    public function store(CreateCustomFieldRequest $request): JsonResponse
    {
        $admin = AdminFactory::createFromModel($request->user());
        $this->authorize->execute($admin, 'custom-field-manage');

        $hostKey = (string) $request->input('host');
        $this->assertFeatureEnabled($hostKey);

        $definition = $this->createDefinition->execute(
            hostKey: $hostKey,
            fieldType: (string) $request->input('field_type'),
            labels: (array) $request->input('labels'),
            isFilterable: $request->boolean('is_filterable'),
            roleRules: (array) $request->input('role_rules', []),
            presentation: $request->only([
                'section', 'slot', 'position', 'icon', 'colour', 'colour_dark',
                'font_size', 'is_required', 'pattern',
            ]),
            actorAdminId: $request->user()->id,
        );

        $this->dispatchReconcile($hostKey, CustomFieldReconcileRun::TRIGGER_SAVE, $request->user()->id);

        return response()->json([
            'success' => true,
            'data' => $this->toArray($definition->fresh(['labels', 'roleRules'])),
        ], 202);
    }

    /** Re-runs the reconcile for a host whose definitions are stuck. */
    public function reconcile(Request $request, string $host): JsonResponse
    {
        $admin = AdminFactory::createFromModel($request->user());
        $this->authorize->execute($admin, 'custom-field-manage');

        $this->hosts->require($host);
        $this->assertFeatureEnabled($host);

        $this->dispatchReconcile($host, CustomFieldReconcileRun::TRIGGER_RETRY, $request->user()->id);

        return response()->json(['success' => true, 'data' => ['host' => $host, 'state' => 'queued']], 202);
    }

    /**
     * `afterCommit`, because every queue connection in config/queue.php sets
     * `after_commit => false` — without it the job races the transaction that
     * wrote the rows it is about to read.
     *
     * The lock key is built HERE, at dispatch, while the tenant connection is
     * the right one. Reading it inside the job's middleware() would read
     * whatever the previous job left on that worker, because Laravel builds
     * the middleware list before any of it runs.
     */
    private function dispatchReconcile(string $hostKey, string $trigger, ?string $actorAdminId): void
    {
        $database = (string) DB::connection('tenant')->getDatabaseName();

        ReconcileTenantFieldSchema::dispatch(
            app()->bound('currentTenant') ? app('currentTenant')->id : null,
            $hostKey,
            $trigger,
            "cf:{$database}:{$hostKey}",
            $actorAdminId,
        )->afterCommit();
    }

    /**
     * A switched-off feature answers 403 with a machine-readable code, never
     * an empty list — the `features.ai_agent` lesson, where a silent skip made
     * the AI chat look broken for three days.
     */
    private function assertFeatureEnabled(string $hostKey): void
    {
        $host = $this->hosts->require($hostKey);

        // Absence means ON, matching the plan form: a tenant provisioned
        // before this feature existed has no such row, and defaulting it off
        // would take the feature from everyone who predates it.
        if ((bool) Settings::get($host->featureFlag(), true)) {
            return;
        }

        abort(response()->json([
            'success' => false,
            'message' => 'Custom fields are not enabled for this workspace.',
            'error' => 'feature_disabled',
            'feature' => $host->featureFlag(),
        ], 403));
    }

    /** @return array<int, array<string, mixed>> */
    private function hostCatalogue(): array
    {
        return array_map(fn ($host) => [
            'key' => $host->key(),
            'slots' => $host->slots(),
            'sections' => $host->sections(),
            'enabled' => (bool) Settings::get($host->featureFlag(), true),
            // Drawn on the screen BEFORE the tenant types anything. Someone
            // who discovers a limit by getting an error mid-save will not
            // trust the feature again.
            'budget' => [
                'max_secondary_indexes' => $host->ceilings()->maxSecondaryIndexes,
                'max_columns' => $host->ceilings()->maxColumns,
                'used' => CustomFieldDefinition::where('host', $host->key())->countsTowardPlanLimit()->count(),
                'plan_limit' => Settings::get('limits.max_custom_fields'),
            ],
        ], $this->hosts->all());
    }

    /** @return array<int, array<string, mixed>> */
    private function typeCatalogue(): array
    {
        return array_map(fn ($type) => [
            'key' => $type->key(),
            'can_filter' => $type->canFilter(),
        ], $this->types->all());
    }

    /**
     * Hand-shaped, matching TemplateController — there is no API Resource
     * layer in this codebase and inventing one here would make this module
     * the odd one out.
     *
     * @return array<string, mixed>
     */
    private function toArray(CustomFieldDefinition $definition): array
    {
        return [
            'id' => $definition->id,
            'host' => $definition->host,
            'field' => $definition->num,
            'key' => $definition->column_name,
            'field_type' => $definition->field_type,
            'is_filterable' => $definition->is_filterable,
            'section' => $definition->section,
            'slot' => $definition->slot,
            'position' => $definition->position,
            'icon' => $definition->icon,
            'colour' => $definition->colour,
            'colour_dark' => $definition->colour_dark,
            'font_size' => $definition->font_size,
            'is_required' => $definition->is_required,
            'pattern' => $definition->pattern,
            'state' => $definition->state,
            // A machine code plus its parameters, so the panel translates it.
            // A frozen-language sentence produced inside a queued job — whose
            // locale is whoever dispatched it — on an otherwise translated
            // screen is a bug in a product that answers in the tenant's
            // language.
            'state_error' => $definition->state_error_code === null ? null : [
                'code' => $definition->state_error_code,
                'params' => $definition->state_error_params ?? [],
            ],
            'is_live' => $definition->state === CustomFieldStates::LIVE,
            'reconciled_at' => $definition->reconciled_at?->toIso8601String(),
            'labels' => $definition->labels->mapWithKeys(fn ($l) => [$l->locale => [
                'label' => $l->label,
                'help_text' => $l->help_text,
                'placeholder' => $l->placeholder,
            ]])->all(),
            'role_rules' => $definition->roleRules
                ->groupBy('rule')
                ->map(fn ($rules) => $rules->pluck('role_id')->values()->all())
                ->all(),
        ];
    }
}
