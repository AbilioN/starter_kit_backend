<?php

namespace App\Infrastructure\CustomFields;

use App\Domain\CustomFields\FieldTypeRegistry;

/**
 * Emits a PHP class specialised to one tenant's exact fields.
 *
 * ## What is generated, and what is not
 *
 * The generated class EXTENDS ReferenceFieldCatalogue and overrides only the
 * methods whose answers can be precomputed. Everything else is inherited, so
 * the two implementations cannot drift on the parts nobody thought to
 * generate — which is the failure mode of hand-writing a second
 * implementation of the same interface.
 *
 * Part 1 overrides `columns()` and `desiredSchema()`, the two the reconciler
 * and the SELECT builder use. Later parts grow the emitted surface alongside
 * the consumer that needs it, and doing so is FREE by design: the generator's
 * fingerprint is part of the class NAME, so a bigger emitter is a class
 * nobody has loaded rather than a redefinition nobody can perform.
 *
 * ## Why the fingerprint is in the name
 *
 * `horizon`, `openai-listener` and `scheduler` are long-lived PHP processes.
 * A class already loaded in one of them cannot be redefined. Putting the
 * generator's fingerprint only in a cache key — which is what the study
 * suggests — leaves a worker executing last version's emitted class until
 * somebody restarts it, which is the study's own "cache that outlived its
 * code" pitfall with an extra step. A new fingerprint is a new class name,
 * so during the window where FPM already runs the new generator and the
 * workers still run the old one, both are simply valid.
 *
 * ## Never eval()
 *
 * A file gets opcache, and a support engineer can read it.
 */
class FieldCatalogueCompiler
{
    public const NAMESPACE = 'TenantFields\\Compiled';

    /**
     * @param  array<int, array<string, mixed>>  $definitions  normalised rows,
     *         the same shape ReferenceFieldCatalogue takes
     */
    public function compile(string $className, array $definitions, string $version, FieldTypeRegistry $types): string
    {
        return $this->render($className, $definitions, $version, $types);
    }

    private function render(string $className, array $definitions, string $version, FieldTypeRegistry $types): string
    {
        $ns = self::NAMESPACE;
        $definitionsLiteral = $this->export($definitions);
        $columnsLiteral = $this->export($this->precomputeColumns($definitions));
        $schemaLiteral = $this->precomputeSchemaLiteral($definitions, $types);
        $stamp = $version;

        return <<<PHP
<?php

/**
 * GENERATED — do not edit.
 *
 * One tenant's custom-field catalogue, compiled by
 * App\\Infrastructure\\CustomFields\\FieldCatalogueCompiler.
 *
 * The class NAME carries three fingerprints: the tenant's database, the
 * version of its definitions, and a hash of the generator itself. Changing
 * any of them produces a different class, which is what lets four long-lived
 * containers disagree about which version they have loaded without any of
 * them fataling.
 *
 * Definitions version: {$stamp}
 */

namespace {$ns};

use App\\Domain\\CustomFields\\ColumnSpec;
use App\\Infrastructure\\CustomFields\\ReferenceFieldCatalogue;

final class {$className} extends ReferenceFieldCatalogue
{
    /** The rows this class was built from, for anything not precomputed. */
    public const DEFINITIONS = {$definitionsLiteral};

    /** host => [column => field type]. Precomputed; the parent derives it. */
    private const COLUMNS = {$columnsLiteral};

    public function version(): string
    {
        return '{$stamp}';
    }

    public function columns(string \$host): array
    {
        return self::COLUMNS[\$host] ?? [];
    }

    public function desiredSchema(string \$host): array
    {
        return match (\$host) {
{$schemaLiteral}
            default => [],
        };
    }
}

PHP;
    }

    /**
     * @param  array<int, array<string, mixed>>  $definitions
     * @return array<string, array<string, string>>
     */
    private function precomputeColumns(array $definitions): array
    {
        $columns = [];

        foreach ($definitions as $definition) {
            // `live` only — the same rule ReferenceFieldCatalogue::readableFor()
            // applies. A pending column does not exist yet and a missing one
            // has been lost; naming either in a SELECT is how a hand-dropped
            // column takes a screen down instead of just being absent.
            if ($definition['state'] !== 'live') {
                continue;
            }

            $columns[$definition['host']][$definition['column']] = $definition['type'];
        }

        return $columns;
    }

    /**
     * The reconciler's input, emitted as literal arrays with the ColumnSpec
     * constructed inline.
     *
     * @param  array<int, array<string, mixed>>  $definitions
     */
    private function precomputeSchemaLiteral(array $definitions, FieldTypeRegistry $types): string
    {
        $byHost = [];

        foreach ($definitions as $definition) {
            if (in_array($definition['state'], ['retired', 'purged'], true)) {
                continue;
            }

            $byHost[$definition['host']][] = $definition;
        }

        $arms = [];

        foreach ($byHost as $host => $rows) {
            $entries = [];

            foreach ($rows as $row) {
                // The spec is derived here from the SAME registry the
                // reference implementation uses, rather than being carried in
                // the normalised row. Two derivations of one decision is how
                // a generated class starts disagreeing with the interpreter
                // it is supposed to reproduce.
                $spec = $types->require($row['type'])->columnSpec((bool) $row['is_filterable']);
                $wantsIndex = ((bool) $row['is_filterable'] && $spec->indexable) ? 'true' : 'false';
                $specLiteral = sprintf(
                    'new ColumnSpec(type: %s, length: %s, precision: %s, scale: %s, indexable: %s)',
                    var_export($spec->type, true),
                    $spec->length === null ? 'null' : (int) $spec->length,
                    $spec->precision === null ? 'null' : (int) $spec->precision,
                    $spec->scale === null ? 'null' : (int) $spec->scale,
                    $spec->indexable ? 'true' : 'false',
                );

                $entries[] = sprintf(
                    "                    ['num' => %d, 'column' => %s, 'type' => %s, 'spec' => %s, 'wants_index' => %s, 'index_name' => %s, 'state' => %s],",
                    (int) $row['num'],
                    var_export($row['column'], true),
                    var_export($row['type'], true),
                    $specLiteral,
                    $wantsIndex,
                    var_export($row['column'].'_idx', true),
                    var_export($row['state'], true),
                );
            }

            $arms[] = sprintf(
                "            %s => [\n%s\n            ],",
                var_export($host, true),
                implode("\n", $entries),
            );
        }

        return implode("\n", $arms);
    }

    /** var_export with the array short syntax, so the emitted file is readable. */
    private function export(mixed $value): string
    {
        $exported = var_export($value, true);
        $exported = preg_replace('/\barray \(/', '[', $exported);
        $exported = preg_replace('/^(\s*)\)/m', '$1]', $exported);

        return str_replace("\n", "\n    ", $exported);
    }

    /**
     * A hash of the generator itself, plus the type classes whose decisions it
     * bakes in.
     *
     * There is no release identifier to use instead — this product has no
     * deploy pipeline; "deploy" is a bind mount plus a container restart. So
     * the fingerprint has to come from the source, and it is memoised per
     * process because it costs a file read.
     *
     * @param  array<int, string>  $typeClasses
     */
    public function fingerprint(array $typeClasses): string
    {
        static $memo = [];

        $key = implode('|', $typeClasses);

        // Contents, not mtimes. A bind-mounted checkout can have any mtime it
        // likes, and a fingerprint that changes when nothing changed forces a
        // pointless recompile in four containers.
        return $memo[$key] ??= substr(hash('sha256', implode('|', array_merge(
            [(string) @file_get_contents(__FILE__)],
            [(string) @file_get_contents(__DIR__.'/ReferenceFieldCatalogue.php')],
            array_map(function (string $class): string {
                $file = (new \ReflectionClass($class))->getFileName();

                return $class.':'.($file ? (string) @file_get_contents($file) : '');
            }, $typeClasses),
        ))), 0, 12);
    }
}
