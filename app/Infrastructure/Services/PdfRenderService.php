<?php

namespace App\Infrastructure\Services;

use App\Application\Services\TemplateEntryTextResolver;
use App\Domain\Services\PdfRenderServiceInterface;
use App\Domain\ValueObjects\TemplateEntry;
use Mpdf\HTMLParserMode;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

class PdfRenderService implements PdfRenderServiceInterface
{
    public function __construct(
        private TemplateEntryTextResolver $entryTextResolver,
    ) {}

    public function renderDocument(string $html): string
    {
        $mpdf = $this->newMpdf();
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    public function renderUnderlay(array $backgroundFilePaths, array $entries, array $values, bool $isPreview = false): string
    {
        $mpdf = $this->newMpdf();

        foreach (array_values($backgroundFilePaths) as $index => $path) {
            $pageNumber = $index + 1;

            // One background file = one page, always page 1 of that file
            // (enforced at upload — see UploadTemplateBackgroundUseCase).
            $mpdf->setSourceFile($path);
            $importedPageId = $mpdf->importPage(1);

            $mpdf->AddPage();
            $mpdf->useTemplate($importedPageId);

            foreach ($entries as $entry) {
                if ($entry->page !== $pageNumber) {
                    continue;
                }

                $this->drawEntry($mpdf, $entry, $values, $isPreview);
            }
        }

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    private function newMpdf(): Mpdf
    {
        // 10mm margins per spec §4a — only meaningful for renderDocument()
        // (no-background HTML docs); underlay mode draws absolutely
        // positioned entries that ignore page margins entirely.
        return new Mpdf(['margin_top' => 10, 'margin_bottom' => 10, 'margin_left' => 10, 'margin_right' => 10]);
    }

    private function drawEntry(Mpdf $mpdf, TemplateEntry $entry, array $values, bool $isPreview): void
    {
        $text = $this->entryTextResolver->resolve($entry, $values);

        if ($text === null) {
            return; // `if` condition didn't match — entry skipped.
        }

        $style = $this->buildEntryStyle($entry, $isPreview);

        $mpdf->WriteHTML(
            '<div style="position:absolute; left:'.$entry->x.'mm; top:'.$entry->y.'mm; '.$style.'">'
            .htmlspecialchars($text, ENT_QUOTES)
            .'</div>',
            HTMLParserMode::HTML_BODY
        );
    }

    private function buildEntryStyle(TemplateEntry $entry, bool $isPreview): string
    {
        $style = [];

        if ($entry->size !== null) {
            $style[] = "font-size:{$entry->size}pt";
        }

        // highlight is preview-only and overrides the entry's own color —
        // the whole point is making the entry easy to spot on a busy form.
        if ($isPreview && $entry->highlight) {
            $style[] = 'color:red';
        } elseif ($entry->color !== null) {
            $style[] = "color:{$entry->color}";
        }

        if ($entry->bg !== null) {
            $style[] = "background-color:{$entry->bg}";
        }

        if ($entry->bold) {
            $style[] = 'font-weight:bold';
        }

        if ($entry->italic) {
            $style[] = 'font-style:italic';
        }

        if ($entry->width !== null) {
            // Text does not wrap; the box clips — matches the legacy
            // behaviour rather than reflowing onto a pre-printed form.
            $style[] = "width:{$entry->width}mm; overflow:hidden; white-space:nowrap";
        }

        if ($entry->space !== null) {
            // Comb spacing approximated via letter-spacing rather than one
            // manually positioned span per character — close enough for
            // filling boxed forms without the extra complexity, revisit if
            // visual fidelity turns out insufficient. boxes (max character
            // count before comb mode is abandoned) is enforced by simply
            // not applying letter-spacing when the text is too long.
            $fitsInBoxes = $entry->boxes === null || mb_strlen($entry->text) <= $entry->boxes;

            if ($fitsInBoxes) {
                $style[] = "letter-spacing:{$entry->space}mm; font-family:monospace";
            }
        }

        return implode('; ', $style).';';
    }
}
