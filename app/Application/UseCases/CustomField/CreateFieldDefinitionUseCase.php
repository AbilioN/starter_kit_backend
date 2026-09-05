<?php

namespace App\Application\UseCases\CustomField;

use App\Application\UseCases\Tenant\EnforcePlanLimitUseCase;
use App\Domain\CustomFields\CustomFieldHostRegistry;
use App\Domain\CustomFields\CustomFieldStates;
use App\Domain\CustomFields\FieldTypeRegistry;
use App\Domain\Exceptions\CustomFieldConflictException;
use App\Infrastructure\CustomFields\CatalogueLoader;
use App\Models\CustomFieldDefinition;
use App\Models\CustomFieldLabel;
use App\Models\CustomFieldRoleRule;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Declares a field. It does NOT create the column.
 *
 * MySQL commits implicitly on DDL, so the definition write and its ALTER
 * cannot be made atomic however the code is arranged — a `DB::transaction`
 * wrapped around both would be a lie that only the SQLite suite could
 * confirm, which is precisely the shape of the notifiable_id outage this
 * project already had. So the write is fast and transactional, the field is
 * born `pending`, and the reconcile is a separate, queued, idempotent step.
 */
class CreateFieldDefinitionUseCase
{
    public function __construct(
        private CustomFieldHostRegistry $hosts,
        private FieldTypeRegistry $types,
        private EnforcePlanLimitUseCase $enforcePlanLimit,
    ) {}

    /**
     * @param  array<string, array{label:string, help_text?:?string, placeholder?:?string}>  $labels  locale => text
     * @param  array<string, array<int, string>>  $roleRules  rule => role ids
     */
    public function execute(
        string $hostKey,
        string $fieldType,
        array $labels,
        bool $isFilterable = false,
        array $roleRules = [],
        array $presentation = [],
        ?string $actorAdminId = null,
    ): CustomFieldDefinition {
        // Both are registry keys, and both must resolve before anything is
        // written: an unknown host is a table name assembled from nothing, and
        // an unknown type is a storage decision nobody can make.
        $this->hosts->require($hostKey);
        $this->types->require($fieldType);

        if ($labels === []) {
            throw new \InvalidArgumentException('A custom field needs a name in at least one language.');
        }

        // Absent means unlimited, which is what EnforcePlanLimitUseCase
        // already means by a missing setting. The count is lazy, so the
        // common unlimited path costs no query.
        $this->enforcePlanLimit->execute(
            'max_custom_fields',
            fn () => CustomFieldDefinition::query()->countsTowardPlanLimit()->count(),
        );

        $definition = DB::connection('tenant')->transaction(function () use (
            $hostKey, $fieldType, $labels, $isFilterable, $roleRules, $presentation, $actorAdminId
        ) {
            $num = $this->allocateNum($hostKey);

            try {
                $definition = CustomFieldDefinition::create([
                    'host' => $hostKey,
                    'num' => $num,
                    'column_name' => "cf_{$num}",
                    'field_type' => $fieldType,
                    'is_filterable' => $isFilterable,
                    'state' => CustomFieldStates::PENDING,
                    'section' => $presentation['section'] ?? null,
                    'slot' => $presentation['slot'] ?? null,
                    'position' => $presentation['position'] ?? 0,
                    'icon' => $presentation['icon'] ?? null,
                    'colour' => $presentation['colour'] ?? null,
                    'colour_dark' => $presentation['colour_dark'] ?? null,
                    'font_size' => $presentation['font_size'] ?? 0,
                    'pattern' => $presentation['pattern'] ?? null,
                    'is_required' => $presentation['is_required'] ?? false,
                    'items' => $presentation['items'] ?? null,
                    'created_by_admin_id' => $actorAdminId,
                ]);
            } catch (QueryException $e) {
                // The unique(host, num) index is the backstop for two admins
                // saving at the same instant. 409 and "try again" is the right
                // answer: the second save is legitimate, it just lost a race.
                throw new CustomFieldConflictException(
                    'Another field was created at the same moment. Please try again.',
                    previous: $e,
                );
            }

            foreach ($labels as $locale => $text) {
                CustomFieldLabel::create([
                    'definition_id' => $definition->id,
                    'locale' => $locale,
                    'label' => $text['label'],
                    'help_text' => $text['help_text'] ?? null,
                    'placeholder' => $text['placeholder'] ?? null,
                ]);
            }

            foreach ($roleRules as $rule => $roleIds) {
                foreach (array_unique($roleIds) as $roleId) {
                    CustomFieldRoleRule::create([
                        'definition_id' => $definition->id,
                        'role_id' => $roleId,
                        'rule' => $rule,
                    ]);
                }
            }

            return $definition;
        });

        // Busted HERE rather than in the caller, so a write path added later
        // cannot forget. The alternative — every controller, command and
        // seeder remembering — is exactly how
        // ChangeTenantSubscriptionPlanUseCase ended up busting keys that had
        // never been written.
        CatalogueLoader::forget();

        return $definition;
    }

    /**
     * The next handle for this host — and `num` is never reused.
     *
     * `lockForUpdate()` because two concurrent saves would otherwise both read
     * the same max. And max() over EVERY row, not just live ones: a retired
     * field's handle stays spent forever, because reusing it would silently
     * hand a new field the old one's parked column, its data and its audit
     * history.
     *
     * This is also why the definitions table has no soft deletes: a trashed
     * row is invisible to max() under the global scope while unique(host, num)
     * still sees it, so the first create after any delete would collide — and
     * keep colliding.
     */
    private function allocateNum(string $hostKey): int
    {
        $max = CustomFieldDefinition::query()
            ->where('host', $hostKey)
            ->lockForUpdate()
            ->max('num');

        return ((int) $max) + 1;
    }
}
