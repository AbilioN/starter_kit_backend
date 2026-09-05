<?php

namespace Tests\Unit\CustomFields;

use App\Application\UseCases\Template\ResolveTemplateLocaleUseCase;
use App\Domain\CustomFields\FieldTypeRegistry;
use App\Domain\CustomFields\Types\TextType;
use App\Infrastructure\CustomFields\FieldCatalogueCompiler;
use App\Infrastructure\CustomFields\ReferenceFieldCatalogue;
use Tests\TestCase;

/**
 * The compiled class and the reference interpreter must give the same answers.
 *
 * This is the only thing that makes a code generator safe to change six months
 * from now, and it exists only because the interpreter was written first. A
 * generator with no golden test is a second implementation of the same rules
 * that nobody can diff.
 *
 * No database: the definitions are hand-built, which is the point — the
 * fixture is the contract, and it grows a case every time a type is added.
 */
class FieldCatalogueCompilerGoldenTest extends TestCase
{
    private FieldTypeRegistry $types;

    private ResolveTemplateLocaleUseCase $locales;

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->types = new FieldTypeRegistry;
        $this->types->register(new TextType);

        // The real cascade, with only the tenant default stubbed — that is the
        // one step that would otherwise need a database. Everything the
        // cascade actually decides (exact match, base-language match, falling
        // back to a translation that exists) is the real implementation.
        $this->locales = new class extends ResolveTemplateLocaleUseCase
        {
            public function tenantDefault(): string
            {
                return 'en';
            }
        };

        $this->directory = storage_path('framework/testing/tenant-fields-golden');

        if (! is_dir($this->directory)) {
            mkdir($this->directory, 0775, true);
        }
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->directory.'/*.php') ?: []);

        parent::tearDown();
    }

    /**
     * One row per decision the definition carries. Every new field type adds a
     * case here, and that is what keeps the generator honest as it grows.
     */
    private function fixture(): array
    {
        return [
            [
                'num' => 1, 'column' => 'cf_1', 'host' => 'appointments', 'type' => 'text',
                'is_filterable' => true, 'state' => 'live', 'items' => null, 'pattern' => null,
                'icon' => 'mdi-file-document', 'colour' => '#185FA5', 'colour_dark' => '#7EB6E8',
                'size' => 0, 'slot' => 'card.badges', 'section' => 'general', 'position' => 1,
                'is_required' => true,
                'labels' => [
                    'en' => ['label' => 'Contract number', 'help_text' => 'Signed contract', 'placeholder' => null],
                    'pt' => ['label' => 'Nº de contrato', 'help_text' => null, 'placeholder' => null],
                ],
                'hidden_role_ids' => ['role-a'], 'readonly_role_ids' => [], 'required_role_ids' => ['role-b'],
            ],
            [
                // Display-only: the branch where filterability changes the
                // column TYPE and not merely the index.
                'num' => 2, 'column' => 'cf_2', 'host' => 'appointments', 'type' => 'text',
                'is_filterable' => false, 'state' => 'live', 'items' => null, 'pattern' => null,
                'icon' => null, 'colour' => null, 'colour_dark' => null,
                'size' => 0, 'slot' => null, 'section' => 'notes', 'position' => 2,
                'is_required' => false,
                'labels' => ['pt' => ['label' => 'Observação', 'help_text' => null, 'placeholder' => null]],
                'hidden_role_ids' => [], 'readonly_role_ids' => [], 'required_role_ids' => [],
            ],
            [
                // Not yet reconciled: must appear in desiredSchema() and must
                // NOT appear in columns(), because its column does not exist.
                'num' => 3, 'column' => 'cf_3', 'host' => 'appointments', 'type' => 'text',
                'is_filterable' => true, 'state' => 'pending', 'items' => null, 'pattern' => null,
                'icon' => null, 'colour' => null, 'colour_dark' => null,
                'size' => 0, 'slot' => null, 'section' => null, 'position' => 3,
                'is_required' => false,
                'labels' => ['en' => ['label' => 'Pending one', 'help_text' => null, 'placeholder' => null]],
                'hidden_role_ids' => [], 'readonly_role_ids' => [], 'required_role_ids' => [],
            ],
            [
                // Parked. Neither readable nor reconcilable — its column has
                // been renamed away, so naming it anywhere would error.
                'num' => 4, 'column' => 'cf_4', 'host' => 'appointments', 'type' => 'text',
                'is_filterable' => false, 'state' => 'retired', 'items' => null, 'pattern' => null,
                'icon' => null, 'colour' => null, 'colour_dark' => null,
                'size' => 0, 'slot' => null, 'section' => null, 'position' => 4,
                'is_required' => false,
                'labels' => ['en' => ['label' => 'Old one', 'help_text' => null, 'placeholder' => null]],
                'hidden_role_ids' => [], 'readonly_role_ids' => [], 'required_role_ids' => [],
            ],
        ];
    }

    private function compiled(array $definitions, string $version = 'goldenv1'): object
    {
        $compiler = new FieldCatalogueCompiler;
        // Unique per run: a class already loaded in this process cannot be
        // redefined, which is the same property the fingerprint-in-the-name
        // scheme relies on in production.
        $class = 'Catalogue_golden_'.substr(sha1(serialize($definitions).$version.uniqid('', true)), 0, 12);

        $file = $this->directory.'/'.$class.'.php';
        file_put_contents($file, $compiler->compile($class, $definitions, $version, $this->types));
        require_once $file;

        $fqcn = FieldCatalogueCompiler::NAMESPACE.'\\'.$class;

        return new $fqcn($definitions, $this->types, $this->locales, $version);
    }

    private function reference(array $definitions, string $version = 'goldenv1'): ReferenceFieldCatalogue
    {
        return new ReferenceFieldCatalogue($definitions, $this->types, $this->locales, $version);
    }

    public function test_columns_agree(): void
    {
        $definitions = $this->fixture();

        $this->assertSame(
            $this->reference($definitions)->columns('appointments'),
            $this->compiled($definitions)->columns('appointments'),
        );
    }

    public function test_desired_schema_agrees_including_the_column_specs(): void
    {
        $definitions = $this->fixture();

        $normalise = fn (array $schema) => array_map(fn (array $e) => [
            'num' => $e['num'],
            'column' => $e['column'],
            'type' => $e['type'],
            // The ColumnSpec is compared by its rendered type, which is the
            // thing the reconciler and information_schema actually agree on.
            'column_type' => $e['spec']->columnType(),
            'indexable' => $e['spec']->indexable,
            'declared_bytes' => $e['spec']->declaredBytes(),
            'wants_index' => $e['wants_index'],
            'index_name' => $e['index_name'],
            'state' => $e['state'],
        ], $schema);

        $this->assertSame(
            $normalise($this->reference($definitions)->desiredSchema('appointments')),
            $normalise($this->compiled($definitions)->desiredSchema('appointments')),
        );
    }

    public function test_fields_agree_in_every_available_locale(): void
    {
        $definitions = $this->fixture();

        foreach (['en', 'pt', 'es', 'pt-BR'] as $locale) {
            $this->assertSame(
                $this->reference($definitions)->fields('appointments', $locale),
                $this->compiled($definitions)->fields('appointments', $locale),
                "The two catalogues disagree in [{$locale}].",
            );
        }
    }

    public function test_an_unknown_host_is_empty_in_both(): void
    {
        $definitions = $this->fixture();

        $this->assertSame([], $this->reference($definitions)->desiredSchema('nope'));
        $this->assertSame([], $this->compiled($definitions)->desiredSchema('nope'));
        $this->assertSame([], $this->compiled($definitions)->columns('nope'));
    }

    public function test_a_pending_field_is_reconcilable_but_not_readable(): void
    {
        $compiled = $this->compiled($this->fixture());

        $this->assertArrayNotHasKey('cf_3', $compiled->columns('appointments'));
        $this->assertContains('cf_3', array_column($compiled->desiredSchema('appointments'), 'column'));
    }

    public function test_a_retired_field_is_neither(): void
    {
        // Its column has been renamed away. Naming it in a SELECT would error,
        // and naming it in a plan would have the reconciler try to recreate it.
        $compiled = $this->compiled($this->fixture());

        $this->assertArrayNotHasKey('cf_4', $compiled->columns('appointments'));
        $this->assertNotContains('cf_4', array_column($compiled->desiredSchema('appointments'), 'column'));
    }

    public function test_an_empty_catalogue_compiles_and_answers_empty(): void
    {
        // The un-migrated tenant, and the tenant that has simply not defined
        // anything. Both must produce a working class rather than a fatal.
        $compiled = $this->compiled([], 'empty');

        $this->assertSame([], $compiled->columns('appointments'));
        $this->assertSame([], $compiled->desiredSchema('appointments'));
        $this->assertSame([], $compiled->fields('appointments', 'en'));
    }
}
