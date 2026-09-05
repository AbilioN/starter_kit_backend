<?php

namespace App\Application\UseCases\CustomField;

use App\Application\CustomFields\FieldViewer;
use App\Application\DTOs\CustomField\FieldDescriptorDto;
use App\Application\DTOs\CustomField\FieldValueDto;
use App\Domain\CustomFields\CustomFieldHostRegistry;
use App\Domain\CustomFields\FieldTypeRegistry;
use App\Infrastructure\CustomFields\CatalogueLoader;
use Illuminate\Database\Eloquent\Model;

/**
 * The ONLY thing that turns stored custom values into something a client sees.
 *
 * Every other path is closed by construction: HasTenantFields hides every
 * `cf_*` attribute from serialisation, derived by regex from the attribute
 * keys rather than from the catalogue, so it holds even when the catalogue is
 * missing, inside Horizon, and on a tenant whose migrations have not run.
 *
 * The viewer is a REQUIRED argument on both methods. That is the enforcement
 * design: a caller who forgets to think about who is asking does not compile,
 * rather than quietly returning everything. The study's third pitfall — "the
 * permission that was only a hidden input" — is a permission enforced in the
 * renderer, and this is the class that keeps the renderer from being where
 * the decision lives.
 *
 * ## Two methods, and why the split matters
 *
 * `context()` answers "what fields exist for this reader" and is sent ONCE per
 * response. `values()` answers "what does this record hold" and is sent per
 * row. A week view carries around a hundred appointments; merging the two
 * would repeat every label, icon and colour pair a hundred times.
 *
 * The context is also what lets a form draw an empty record: the controls
 * exist because the descriptors do.
 */
class ProjectCustomFieldsUseCase
{
    public function __construct(
        private CatalogueLoader $catalogues,
        private FieldTypeRegistry $types,
        private CustomFieldHostRegistry $hosts,
    ) {}

    /**
     * The fields this reader may see, already resolved for their language.
     *
     * @param  string|null  $slot  when given, only fields placed in that slot
     * @return array<int, array<string, mixed>>
     */
    public function context(string $hostKey, FieldViewer $viewer, ?string $slot = null): array
    {
        $sections = $this->hosts->get($hostKey)?->sections() ?? [];

        return array_map(
            fn (array $field) => $this->describe($field, $viewer, $sections)->toArray(),
            $this->visibleFields($hostKey, $viewer, $slot),
        );
    }

    /**
     * One record's values, compact, joined to the context by `field`.
     *
     * @return array<int, array<string, mixed>>
     */
    public function values(string $hostKey, Model $record, FieldViewer $viewer, ?string $slot = null): array
    {
        // Read off the raw attributes rather than through an accessor, because
        // getHidden() deliberately hides them from every normal path —
        // including toArray(), which is what makes the leak impossible
        // everywhere else.
        $stored = method_exists($record, 'tenantFieldValues') ? $record->tenantFieldValues() : [];
        $locale = app()->getLocale();

        $values = [];

        foreach ($this->visibleFields($hostKey, $viewer, $slot) as $field) {
            $raw = $stored[$field['column']] ?? null;
            $type = $this->types->require($field['type']);

            $values[] = (new FieldValueDto(
                field: $field['num'],
                key: $field['column'],
                value: $type->toMachineValue($raw),
                text: $type->toText($raw, $locale, $field),
            ))->toArray();
        }

        return $values;
    }

    /**
     * The columns this viewer may WRITE.
     *
     * Hidden and readonly both drop out, so a submitted value for either is
     * discarded rather than stored. The caller reports what it dropped — a
     * stale form must not be unsubmittable, and a silent drop is the "looked
     * like it worked" class this feature refuses everywhere else.
     *
     * @return array<int, string>
     */
    public function writableColumns(string $hostKey, FieldViewer $viewer): array
    {
        $columns = [];

        foreach ($this->visibleFields($hostKey, $viewer) as $field) {
            if (! $viewer->bypass && $viewer->matches($field['readonly_role_ids'])) {
                continue;
            }

            $columns[] = $field['column'];
        }

        return $columns;
    }

    /**
     * Fields this reader may see at all.
     *
     * A hidden field is OMITTED, not nulled. A null still says the field
     * exists, which is information a hidden field is not supposed to leak —
     * and it is what would make a filter on one an oracle.
     *
     * @return array<int, array<string, mixed>>
     */
    private function visibleFields(string $hostKey, FieldViewer $viewer, ?string $slot = null): array
    {
        $fields = $this->catalogues->load()->fields($hostKey, app()->getLocale());

        return array_values(array_filter($fields, function (array $field) use ($viewer, $slot) {
            if ($slot !== null && ($field['slot'] ?? null) !== $slot) {
                return false;
            }

            return $viewer->bypass || ! $viewer->matches($field['hidden_role_ids']);
        }));
    }

    /** @param array<string, string> $sections */
    private function describe(array $field, FieldViewer $viewer, array $sections = []): FieldDescriptorDto
    {
        return new FieldDescriptorDto(
            field: $field['num'],
            key: $field['column'],
            type: $field['type'],
            label: $field['label'],
            helpText: $field['help_text'],
            icon: $field['icon'],
            colour: $field['colour'],
            colourDark: $field['colour_dark'],
            size: $field['size'],
            slot: $field['slot'],
            section: $field['section'],
            sectionLabel: $field['section'] === null ? null : ($sections[$field['section']] ?? null),
            position: $field['position'],
            // Required for everyone, OR required for one of this reader's
            // roles. Deny-wins applies to obligations the same way it applies
            // to denials: they are the same operator on a differently-signed
            // list.
            required: $field['is_required'] || (! $viewer->bypass && $viewer->matches($field['required_role_ids'])),
            editable: $viewer->bypass || ! $viewer->matches($field['readonly_role_ids']),
            items: $field['items'],
        );
    }
}
