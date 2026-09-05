<?php

namespace Tests\Feature\CustomFields;

use App\Application\UseCases\CustomField\CreateFieldDefinitionUseCase;
use App\Domain\CustomFields\CustomFieldStates;
use App\Infrastructure\CustomFields\CatalogueLoader;
use Illuminate\Support\Facades\DB;
use Tests\TenantTestCase;

/**
 * What the catalogue costs, and what invalidates it.
 *
 * The catalogue sits on the hot path of every agenda, list and form in a
 * multi-tenant product, so "how many queries" is not a detail. An unmeasured
 * optimisation is not an optimisation, which is what these tests are for.
 */
class CatalogueCacheTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsTenant('cfcache');
        CatalogueLoader::forget();
    }

    private function defineField(string $label): void
    {
        $definition = app(CreateFieldDefinitionUseCase::class)->execute(
            hostKey: 'appointments',
            fieldType: 'text',
            labels: ['en' => ['label' => $label]],
        );

        // The state change is a direct model write rather than a use case, so
        // this one still busts by hand. CreateFieldDefinitionUseCase does it
        // itself — which is what the second test below actually checks.
        $definition->update(['state' => CustomFieldStates::LIVE]);

        CatalogueLoader::forget();
    }

    /** @return array{0: mixed, 1: int} the result and how many queries it cost */
    private function countingQueries(callable $callback): array
    {
        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        $result = $callback();

        return [$result, $queries];
    }

    public function test_a_warm_catalogue_costs_no_queries_at_all(): void
    {
        $this->defineField('Contract number');

        $loader = app(CatalogueLoader::class);

        // First load pays for the rows.
        [, $cold] = $this->countingQueries(fn () => $loader->load());
        $this->assertGreaterThan(0, $cold, 'A cold load must actually read the definitions.');

        // The per-process memo is what makes a second call inside ONE request
        // free. Clearing it leaves the Redis entry, which is what makes the
        // next REQUEST free — including in horizon, the scheduler and the
        // openai-listener, which never see this process at all.
        $this->forgetProcessMemoOnly();

        [$catalogue, $warm] = $this->countingQueries(fn () => $loader->load());

        $this->assertSame(0, $warm, 'A warm catalogue must not touch the database.');
        $this->assertSame(['cf_1' => 'text'], $catalogue->columns('appointments'));
    }

    public function test_defining_a_field_busts_the_cache(): void
    {
        $this->defineField('Contract number');

        $loader = app(CatalogueLoader::class);
        $this->assertCount(1, $loader->load()->columns('appointments'));

        // CreateFieldDefinitionUseCase busts the cache itself; the second
        // field must be visible immediately rather than in an hour.
        $this->defineField('Site reference');

        $this->assertCount(2, $loader->load()->columns('appointments'));
    }

    public function test_the_cached_version_and_the_compiled_class_can_never_disagree(): void
    {
        // The version is derived from the cached CONTENT, not from a separate
        // query. That is what makes a stale cache serve a coherent older
        // catalogue rather than a class whose baked-in literals contradict
        // its own definitions — which is the failure this test exists to
        // pin down, because it already happened once when the version came
        // from max(updated_at) and two writes landed in the same second.
        $this->defineField('Contract number');

        $catalogue = app(CatalogueLoader::class)->load();

        $this->assertSame(
            array_keys($catalogue->columns('appointments')),
            array_column($catalogue->desiredSchema('appointments'), 'column'),
            'The compiled class must describe exactly the fields it was built from.',
        );
    }

    /**
     * Clears the per-process memo without touching Redis, which is how a
     * second HTTP request in another container sees this tenant.
     */
    private function forgetProcessMemoOnly(): void
    {
        $property = new \ReflectionProperty(CatalogueLoader::class, 'instances');
        $property->setValue(null, []);
    }
}
