<?php

namespace Tests\Feature\Template;

use App\Domain\Services\PdfRenderServiceInterface;
use App\Domain\ValueObjects\TemplateEntry;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use Tests\TenantTestCase;

class PdfRenderServiceTest extends TenantTestCase
{
    private PdfRenderServiceInterface $renderer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsTenant();

        $this->renderer = app(PdfRenderServiceInterface::class);
    }

    public function test_render_document_produces_a_valid_pdf(): void
    {
        $pdf = $this->renderer->renderDocument('<h1>Hello World</h1><p>A generated document.</p>');

        $this->assertStringStartsWith('%PDF-', $pdf);
    }

    public function test_render_underlay_stamps_entries_onto_a_background(): void
    {
        $backgroundPath = $this->makeBackgroundPdf();

        $entries = [
            TemplateEntry::fromArray(['x' => 20, 'y' => 30, 'text' => 'Stamped Value', 'page' => 1]),
        ];

        $pdf = $this->renderer->renderUnderlay([$backgroundPath], $entries, []);

        $this->assertStringStartsWith('%PDF-', $pdf);
    }

    public function test_render_underlay_handles_multiple_background_pages_in_order(): void
    {
        $page1 = $this->makeBackgroundPdf('Page One Background');
        $page2 = $this->makeBackgroundPdf('Page Two Background');

        $entries = [
            TemplateEntry::fromArray(['x' => 10, 'y' => 10, 'text' => 'On page 1', 'page' => 1]),
            TemplateEntry::fromArray(['x' => 10, 'y' => 10, 'text' => 'On page 2', 'page' => 2]),
        ];

        $pdf = $this->renderer->renderUnderlay([$page1, $page2], $entries, []);

        $this->assertStringStartsWith('%PDF-', $pdf);
        // 2 background pages imported -> at least 2 pages in the output.
        $this->assertGreaterThanOrEqual(2, preg_match_all('/\/Type\s*\/Page[^s]/', $pdf));
    }

    public function test_render_underlay_substitutes_placeholders_in_entry_text(): void
    {
        $backgroundPath = $this->makeBackgroundPdf();

        $entries = [
            TemplateEntry::fromArray(['x' => 20, 'y' => 30, 'text' => 'Hello {name}', 'page' => 1]),
        ];

        // Can't easily assert the drawn text from compressed PDF bytes -
        // this just proves resolution + rendering doesn't blow up with a
        // real placeholder present, complementing TemplateEntryTextResolverTest
        // which covers the substitution logic itself in isolation.
        $pdf = $this->renderer->renderUnderlay($backgroundPath ? [$backgroundPath] : [], $entries, ['{name}' => 'Jean']);

        $this->assertStringStartsWith('%PDF-', $pdf);
    }

    public function test_render_underlay_skips_an_entry_whose_if_condition_fails(): void
    {
        $backgroundPath = $this->makeBackgroundPdf();

        $entries = [
            TemplateEntry::fromArray(['x' => 10, 'y' => 10, 'text' => 'Shown', 'page' => 1, 'if' => '{status}:active']),
            TemplateEntry::fromArray(['x' => 10, 'y' => 20, 'text' => 'Hidden', 'page' => 1, 'if' => '{status}:inactive']),
        ];

        $pdf = $this->renderer->renderUnderlay([$backgroundPath], $entries, ['{status}' => 'active']);

        // No exception, still a valid PDF - the actual skip logic is
        // covered directly in TemplateEntryTextResolverTest.
        $this->assertStringStartsWith('%PDF-', $pdf);
    }

    private function makeBackgroundPdf(string $label = 'Background Form'): string
    {
        $mpdf = new Mpdf();
        $mpdf->WriteHTML("<h2>{$label}</h2>");

        $path = storage_path('app/sqlite/test-background-'.uniqid().'.pdf');
        \Illuminate\Support\Facades\File::ensureDirectoryExists(dirname($path));
        $mpdf->Output($path, Destination::FILE);

        return $path;
    }
}
