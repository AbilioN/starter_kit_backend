<?php

namespace Tests\Unit\Application\Services;

use App\Application\Services\TemplateEntryTextResolver;
use App\Domain\ValueObjects\TemplateEntry;
use PHPUnit\Framework\TestCase;

class TemplateEntryTextResolverTest extends TestCase
{
    private TemplateEntryTextResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new TemplateEntryTextResolver();
    }

    private function entry(array $overrides = []): TemplateEntry
    {
        return TemplateEntry::fromArray(array_merge([
            'x' => 10, 'y' => 20, 'text' => 'hello', 'page' => 1,
        ], $overrides));
    }

    public function test_literal_text_passes_through(): void
    {
        $result = $this->resolver->resolve($this->entry(['text' => 'Literal']), []);

        $this->assertSame('Literal', $result);
    }

    public function test_placeholders_in_entry_text_are_substituted(): void
    {
        $result = $this->resolver->resolve(
            $this->entry(['text' => '{last_name}, {first_name}']),
            ['{last_name}' => 'Sample', '{first_name}' => 'Jean'],
        );

        $this->assertSame('Sample, Jean', $result);
    }

    public function test_if_condition_matching_keeps_the_entry(): void
    {
        $result = $this->resolver->resolve(
            $this->entry(['text' => 'X', 'if' => '{status}:3']),
            ['{status}' => '3'],
        );

        $this->assertSame('X', $result);
    }

    public function test_if_condition_not_matching_skips_the_entry(): void
    {
        $result = $this->resolver->resolve(
            $this->entry(['text' => 'X', 'if' => '{status}:3']),
            ['{status}' => '1'],
        );

        $this->assertNull($result);
    }

    public function test_digits_strips_non_numeric_characters(): void
    {
        $result = $this->resolver->resolve(
            $this->entry(['text' => '{phone}', 'digits' => '1']),
            ['{phone}' => '(555) 123-4567'],
        );

        $this->assertSame('5551234567', $result);
    }

    public function test_format_floor_coerces_a_decimal(): void
    {
        $result = $this->resolver->resolve($this->entry(['text' => '19.75', 'format' => 'floor']), []);

        $this->assertSame('19', $result);
    }

    public function test_format_round_coerces_a_decimal(): void
    {
        $result = $this->resolver->resolve($this->entry(['text' => '19.5', 'format' => 'round']), []);

        $this->assertSame('20', $result);
    }

    public function test_format_is_ignored_for_non_numeric_text(): void
    {
        $result = $this->resolver->resolve($this->entry(['text' => 'not a number', 'format' => 'round']), []);

        $this->assertSame('not a number', $result);
    }

    public function test_month_full_name_lowercase(): void
    {
        $result = $this->resolver->resolve($this->entry(['text' => '3', 'month' => 'en']), []);

        $this->assertSame('march', $result);
    }

    public function test_month_full_name_titlecase_french(): void
    {
        $result = $this->resolver->resolve($this->entry(['text' => '2', 'month' => 'Fr']), []);

        $this->assertSame('Février', $result);
    }

    public function test_month_full_name_uppercase_spanish(): void
    {
        $result = $this->resolver->resolve($this->entry(['text' => '1', 'month' => 'ES']), []);

        $this->assertSame('ENERO', $result);
    }

    public function test_slice_takes_a_positive_range(): void
    {
        $result = $this->resolver->resolve($this->entry(['text' => '20260315', 'slice' => '4:8']), []);

        $this->assertSame('0315', $result);
    }

    public function test_slice_takes_a_negative_end(): void
    {
        $result = $this->resolver->resolve($this->entry(['text' => 'ABCDEFGH', 'slice' => '0:-4']), []);

        $this->assertSame('ABCD', $result);
    }

    public function test_transforms_chain_digits_then_slice(): void
    {
        $result = $this->resolver->resolve(
            $this->entry(['text' => '{account}', 'digits' => '1', 'slice' => '0:4']),
            ['{account}' => 'AB-1234-56'],
        );

        $this->assertSame('1234', $result);
    }
}
