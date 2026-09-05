<?php

namespace App\Infrastructure\CustomFields;

use App\Application\UseCases\Template\ResolveTemplateLocaleUseCase;
use App\Domain\CustomFields\CompiledCatalogueInterface;
use App\Domain\CustomFields\FieldTypeRegistry;

/**
 * The readable, obviously-correct catalogue. Written FIRST, on purpose.
 *
 * FieldCatalogueCompiler generates a class that answers the same questions
 * from baked-in literals; a golden test asserts the two agree. That test is
 * the only thing that makes a code generator safe to change six months from
 * now, and it can only exist if there is something to compare against — which
 * is why this class is not scaffolding to be deleted once the compiler works.
 *
 * It is also the runtime fallback. If a compiled class cannot be written or
 * loaded, CatalogueLoader falls back to this — and says so, in the log and in
 * the health payload. A silent fallback would turn "the generator broke" into
 * "the app is mysteriously slower", which is the kind of degradation this
 * project has already paid for once.
 *
 * ## The shape of a definition row
 *
 * Both implementations agree on one normalised array per field, which is the
 * real contract between the reader, this class and the emitted code:
 *
 *   num, column, host, type, is_filterable, state, items, pattern,
 *   icon, colour, colour_dark, size, slot, section, position, is_required,
 *   labels: [locale => [label, help_text, placeholder]],
 *   hidden_role_ids, readonly_role_ids, required_role_ids
 */
class ReferenceFieldCatalogue implements CompiledCatalogueInterface
{
    /**
     * @param  array<int, array<string, mixed>>  $definitions  normalised rows
     */
    public function __construct(
        protected array $definitions,
        protected FieldTypeRegistry $types,
        protected ResolveTemplateLocaleUseCase $locales,
        protected string $version,
    ) {}

    public function version(): string
    {
        return $this->version;
    }

    public function hosts(): array
    {
        return array_values(array_unique(array_column($this->definitions, 'host')));
    }

    public function locales(): array
    {
        $locales = [];

        foreach ($this->definitions as $definition) {
            foreach (array_keys($definition['labels'] ?? []) as $locale) {
                $locales[$locale] = true;
            }
        }

        return array_keys($locales);
    }

    public function columns(string $host): array
    {
        $columns = [];

        foreach ($this->readableFor($host) as $definition) {
            $columns[$definition['column']] = $definition['type'];
        }

        return $columns;
    }

    public function desiredSchema(string $host): array
    {
        $schema = [];

        foreach ($this->definitions as $definition) {
            if ($definition['host'] !== $host) {
                continue;
            }

            // Retired and purged rows are excluded: their column is either
            // renamed away or gone, and in both cases the reconciler's job is
            // to leave it alone rather than to recreate it.
            if (in_array($definition['state'], ['retired', 'purged'], true)) {
                continue;
            }

            $type = $this->types->require($definition['type']);
            $spec = $type->columnSpec((bool) $definition['is_filterable']);

            $schema[] = [
                'num' => (int) $definition['num'],
                'column' => $definition['column'],
                'type' => $definition['type'],
                'spec' => $spec,
                // Filterability is what the tenant asked for; indexability is
                // what the column can actually take. A type that cannot be
                // indexed never gets an index promised for it.
                'wants_index' => (bool) $definition['is_filterable'] && $spec->indexable,
                'index_name' => $definition['column'].'_idx',
                'state' => $definition['state'],
            ];
        }

        return $schema;
    }

    public function fields(string $host, string $locale): array
    {
        $fields = [];

        foreach ($this->readableFor($host) as $definition) {
            $fields[] = [
                'num' => (int) $definition['num'],
                'column' => $definition['column'],
                'type' => $definition['type'],
                'label' => $this->label($definition, $locale),
                'help_text' => $this->labelPart($definition, $locale, 'help_text'),
                'icon' => $definition['icon'] ?? null,
                'colour' => $definition['colour'] ?? null,
                'colour_dark' => $definition['colour_dark'] ?? null,
                'size' => (int) ($definition['size'] ?? 0),
                'slot' => $definition['slot'] ?? null,
                'section' => $definition['section'] ?? null,
                'position' => (int) ($definition['position'] ?? 0),
                'is_required' => (bool) ($definition['is_required'] ?? false),
                'items' => $definition['items'] ?? null,
                'hidden_role_ids' => $definition['hidden_role_ids'] ?? [],
                'readonly_role_ids' => $definition['readonly_role_ids'] ?? [],
                'required_role_ids' => $definition['required_role_ids'] ?? [],
            ];
        }

        usort($fields, fn ($a, $b) => [$a['position'], $a['num']] <=> [$b['position'], $b['num']]);

        return $fields;
    }

    /**
     * Fields whose column can actually be read right now.
     *
     * `live` only. A `pending` field has no column yet and a `missing` one has
     * lost it — naming either in a SELECT is how a hand-dropped column takes
     * a screen down with SQLSTATE 42S22 instead of just being absent.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function readableFor(string $host): array
    {
        return array_values(array_filter(
            $this->definitions,
            fn (array $d) => $d['host'] === $host && $d['state'] === 'live',
        ));
    }

    /**
     * The tenant's own name for this field, in the reader's language.
     *
     * Falls through ResolveTemplateLocaleUseCase — the cascade that already
     * exists (what was asked for, then the tenant's default, then whatever
     * translation was actually written) rather than a second one invented
     * here. Its third step is the one that matters: a tenant enables four
     * languages, writes the label in one, and a reader in another must still
     * see a field name rather than a blank.
     */
    protected function label(array $definition, string $locale): string
    {
        return $this->labelPart($definition, $locale, 'label')
            // A definition with no labels at all cannot be created — that is
            // refused at save. This is the belt for a row that predates the
            // rule or lost its labels to a partial restore: a column handle is
            // a poor name, and it is much better than an empty chip.
            ?? $definition['column'];
    }

    protected function labelPart(array $definition, string $locale, string $part): ?string
    {
        $labels = $definition['labels'] ?? [];

        if ($labels === []) {
            return null;
        }

        $chosen = $this->locales->execute(array_keys($labels), $locale);

        return $chosen === null ? null : ($labels[$chosen][$part] ?? null);
    }
}
