<?php

namespace App\Application\UseCases\Template;

use App\Application\Services\PlaceholderResolverService;
use App\Domain\Entities\File as FileEntity;
use App\Domain\Entities\Template;
use App\Domain\Exceptions\TemplateNotFoundException;
use App\Domain\Repositories\TemplateRepositoryInterface;
use App\Domain\Services\PdfRenderServiceInterface;
use App\Domain\ValueObjects\TemplateEntry;
use Illuminate\Support\Facades\File as FileFacade;
use Illuminate\Support\Facades\Storage;

/**
 * The generation pipeline (spec §10): resolve values -> expand includes ->
 * strict check -> substitute -> render for the type. Returns the rendered
 * artifact — text, HTML, or PDF bytes. Does NOT deliver it (email/SMS/AI
 * dispatch, storing on a record, etc.) — that's explicitly deferred until
 * a real MergeContext entity exists to know what's being delivered to whom.
 *
 * $isPreview both (a) tells the PDF renderer to honour `highlight` entries
 * and (b) fills any empty merge field with «field_name» so an author can
 * see it on the page — spec §6's "preview loop" behaviour. There is no
 * separate GeneratePreviewTemplateUseCase: preview and real rendering are
 * the same pipeline with one flag, not two implementations to keep in sync.
 */
class RenderTemplateUseCase
{
    public function __construct(
        private TemplateRepositoryInterface $templateRepository,
        private PlaceholderResolverService $resolver,
        private PdfRenderServiceInterface $pdfRenderer,
        private GetTemplateBackgroundFilesUseCase $getBackgroundFiles,
    ) {}

    /**
     * @param array<string, string> $promptValues
     * @return array{content_type: string, content: string}
     */
    public function execute(
        string $templateId,
        int|string|null $recordId = null,
        array $promptValues = [],
        bool $isPreview = false,
    ): array {
        $template = $this->templateRepository->findById($templateId);

        if (! $template) {
            throw new TemplateNotFoundException("Template {$templateId} not found.");
        }

        if ($template->type === 'pdf' && $template->bodyFormat === 'positions') {
            return $this->renderUnderlayPdf($template, $recordId, $isPreview);
        }

        if ($template->type === 'pdf' && $template->bodyFormat === 'html') {
            return $this->renderDocumentPdf($template, $recordId, $promptValues, $isPreview);
        }

        return $this->renderText($template, $recordId, $promptValues, $isPreview);
    }

    private function renderText(Template $template, int|string|null $recordId, array $promptValues, bool $isPreview): array
    {
        $content = $this->resolver->resolve($template->body ?? '', $recordId, $promptValues, $isPreview);

        return [
            'content_type' => $template->bodyFormat === 'html' ? 'text/html' : 'text/plain',
            'content' => $content,
        ];
    }

    private function renderDocumentPdf(Template $template, int|string|null $recordId, array $promptValues, bool $isPreview): array
    {
        $html = $this->resolver->resolve($template->body ?? '', $recordId, $promptValues, $isPreview);

        return [
            'content_type' => 'application/pdf',
            'content' => $this->pdfRenderer->renderDocument($html),
        ];
    }

    private function renderUnderlayPdf(Template $template, int|string|null $recordId, bool $isPreview): array
    {
        $entries = array_map(
            fn (array $raw) => TemplateEntry::fromArray($raw),
            json_decode($template->body ?? '[]', true) ?? [],
        );

        $backgroundFiles = $this->getBackgroundFiles->execute($template->id);
        $paths = array_map(fn (FileEntity $file) => $this->resolveLocalPath($file), $backgroundFiles);

        $values = $this->resolver->buildValueMap($recordId, $isPreview);

        try {
            return [
                'content_type' => 'application/pdf',
                'content' => $this->pdfRenderer->renderUnderlay($paths, $entries, $values, $isPreview),
            ];
        } finally {
            foreach ($paths as $path) {
                if (str_starts_with($path, sys_get_temp_dir())) {
                    @unlink($path);
                }
            }
        }
    }

    /**
     * mPDF's import needs a real local filesystem path — download s3-backed
     * backgrounds to a throwaway temp file first (cleaned up by the caller).
     */
    private function resolveLocalPath(FileEntity $file): string
    {
        if ($file->disk === 'local') {
            return Storage::disk('local')->path($file->path);
        }

        $tempPath = sys_get_temp_dir().'/template-bg-'.uniqid().'.pdf';
        FileFacade::put($tempPath, Storage::disk($file->disk)->get($file->path));

        return $tempPath;
    }
}
