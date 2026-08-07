<?php

namespace Tests\Feature\Template;

use App\Application\Services\PlaceholderResolverService;
use App\Domain\Exceptions\StrictFieldEmptyException;
use App\Domain\Repositories\TemplateRepositoryInterface;
use Tests\TenantTestCase;

/**
 * Exercises the resolver against the real StubMergeContext + real
 * TemplateRepository (backed by the tenant DB) rather than mocking them —
 * both are cheap, deterministic, and this is exactly the combination the
 * module is designed to be fully testable with before any real business
 * entity exists.
 */
class PlaceholderResolverServiceTest extends TenantTestCase
{
    private PlaceholderResolverService $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsTenant();

        $this->resolver = app(PlaceholderResolverService::class);
    }

    public function test_it_substitutes_plain_fields(): void
    {
        $result = $this->resolver->resolve('Hello {first_name} {last_name}!', recordId: 1);

        $this->assertSame('Hello Jean Sample!', $result);
    }

    public function test_unknown_fields_resolve_to_empty_string(): void
    {
        $result = $this->resolver->resolve('Value: {does_not_exist}', recordId: 1);

        $this->assertSame('Value: ', $result);
    }

    public function test_strict_field_passes_when_filled(): void
    {
        $result = $this->resolver->resolve('Name: {first_name!}', recordId: 1);

        $this->assertSame('Name: Jean', $result);
    }

    public function test_strict_field_throws_when_empty(): void
    {
        // Not in StubMergeContext::fields() at all - falls back to the raw
        // key as the label since there's nothing to look up.
        $this->expectException(StrictFieldEmptyException::class);
        $this->expectExceptionMessage("The required field 'zip_missing' is not filled in for this record.");

        $this->resolver->resolve('Zip: {zip_missing!}', recordId: 1);
    }

    public function test_strict_field_error_uses_the_catalogue_label_when_known(): void
    {
        // A field the catalogue knows about (proper label) but that
        // resolves empty for this record - StubMergeContext's fixed
        // values never happen to be empty, so this uses a throwaway
        // MergeContext double to exercise the label lookup specifically.
        $this->app->instance(\App\Domain\Services\MergeContextInterface::class, new class implements \App\Domain\Services\MergeContextInterface {
            public function fields(): array
            {
                return [['key' => 'signature', 'label' => 'Signature']];
            }

            public function values(int|string $recordId): array
            {
                return ['{signature}' => ''];
            }
        });

        $resolver = app(PlaceholderResolverService::class);

        $this->expectException(StrictFieldEmptyException::class);
        $this->expectExceptionMessage("The required field 'Signature' is not filled in for this record.");

        $resolver->resolve('Sign here: {signature!}', recordId: 1);
    }

    public function test_prompt_values_are_supplied_by_the_caller_not_the_record(): void
    {
        $result = $this->resolver->resolve('Reason: {prompt:reason}', recordId: 1, promptValues: ['reason' => 'Renewal']);

        $this->assertSame('Reason: Renewal', $result);
    }

    public function test_missing_prompt_value_resolves_to_empty_string(): void
    {
        $result = $this->resolver->resolve('Reason: {prompt:reason}', recordId: 1);

        $this->assertSame('Reason: ', $result);
    }

    public function test_it_expands_an_included_template_recursively(): void
    {
        $repo = app(TemplateRepositoryInterface::class);

        $signature = $repo->create(
            name: 'Signature Block',
            type: 'text_email',
            bodyFormat: 'text',
            body: 'Best regards, {first_name}',
        );

        $header = $repo->create(
            name: 'Header Block',
            type: 'text_email',
            bodyFormat: 'text',
            body: "Dear {first_name},\n\n{@{$signature->id}}",
        );

        $result = $this->resolver->resolve("{@{$header->id}}", recordId: 1);

        $this->assertSame("Dear Jean,\n\nBest regards, Jean", $result);
    }

    public function test_a_strict_field_inside_an_include_is_still_validated(): void
    {
        $repo = app(TemplateRepositoryInterface::class);

        $included = $repo->create(
            name: 'Strict Include',
            type: 'text_email',
            bodyFormat: 'text',
            body: 'Zip: {zip_missing!}',
        );

        $this->expectException(StrictFieldEmptyException::class);

        $this->resolver->resolve("{@{$included->id}}", recordId: 1);
    }

    public function test_company_placeholder_resolves_from_the_current_tenant(): void
    {
        // currentTenant is normally bound by the IdentifyTenant middleware
        // during a real HTTP request - resolve() is called directly here,
        // so bind it the same way for this one test.
        app()->instance('currentTenant', \App\Models\Tenant::on('landlord')->where('subdomain', 'testing')->first());

        $result = $this->resolver->resolve('Sent by {company}', recordId: 1);

        $this->assertSame('Sent by Testing Tenant', $result);
    }
}
