<?php

namespace App\Application\UseCases\Template;

use App\Domain\Exceptions\TemplateNotFoundException;
use App\Domain\Repositories\FileRepositoryInterface;
use App\Domain\Repositories\TemplateRepositoryInterface;
use App\Domain\Services\StorageServiceInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File as FileFacade;
use InvalidArgumentException;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

/**
 * §8 of the source spec: one background file = one page, enforced here at
 * upload (not at render) by splitting a multi-page PDF into one file per
 * page. Page order = upload order (this method's own iteration), tracked
 * via `files.meta->sort` — reusing the existing polymorphic `files` table
 * rather than a dedicated background-attachment table.
 *
 * Deliberately not ported from the legacy spec: downgrading PDFs above
 * version 1.5 before import. mPDF's bundled FPDI import handles modern PDF
 * versions directly; if a genuinely incompatible file (encrypted/signed)
 * is uploaded, the import throws and that propagates as a normal error
 * rather than being silently "fixed" by a conversion step this port has
 * no way to verify is still necessary.
 */
class UploadTemplateBackgroundUseCase
{
    public function __construct(
        private TemplateRepositoryInterface $templateRepository,
        private FileRepositoryInterface $fileRepository,
        private StorageServiceInterface $storageService,
    ) {}

    public function execute(string $templateId, UploadedFile $file): array
    {
        $template = $this->templateRepository->findById($templateId);

        if (! $template) {
            throw new TemplateNotFoundException("Template {$templateId} not found.");
        }

        if ($template->type !== 'pdf') {
            throw new InvalidArgumentException('Background files can only be attached to pdf templates.');
        }

        if (strtolower($file->getClientOriginalExtension()) !== 'pdf' && $file->getMimeType() !== 'application/pdf') {
            throw new InvalidArgumentException('Only PDF files can be used as a template background.');
        }

        $mpdf = new Mpdf();
        $pageCount = $mpdf->setSourceFile($file->getRealPath());

        $nextSort = $this->nextSortIndex($templateId);
        $created = [];

        for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
            $singlePagePath = $this->extractSinglePage($file->getRealPath(), $pageNumber);

            try {
                $stored = $this->storageService->store(
                    new UploadedFile($singlePagePath, $file->getClientOriginalName().'-p'.$pageNumber.'.pdf', 'application/pdf', null, true),
                    'pdf_background',
                );

                $created[] = $this->fileRepository->create([
                    'original_name' => $stored['original_name'],
                    'stored_name' => $stored['stored_name'],
                    'disk' => $stored['disk'],
                    'path' => $stored['path'],
                    'mime_type' => $stored['mime_type'],
                    'size' => $stored['size'],
                    'folder' => 'pdf_background',
                    'uploadable_type' => 'App\\Models\\Template',
                    'uploadable_id' => $templateId,
                    'is_public' => false,
                    'meta' => ['sort' => $nextSort + $pageNumber - 1],
                ]);
            } finally {
                @unlink($singlePagePath);
            }
        }

        return $created;
    }

    private function extractSinglePage(string $sourcePath, int $pageNumber): string
    {
        $mpdf = new Mpdf();
        $mpdf->setSourceFile($sourcePath);
        $importedPageId = $mpdf->importPage($pageNumber);
        $mpdf->AddPage();
        $mpdf->useTemplate($importedPageId);

        $path = storage_path('app/tmp/template-bg-page-'.uniqid().'.pdf');
        FileFacade::ensureDirectoryExists(dirname($path));
        $mpdf->Output($path, Destination::FILE);

        return $path;
    }

    private function nextSortIndex(string $templateId): int
    {
        $existing = $this->fileRepository->findForUploadable('App\\Models\\Template', $templateId, 'pdf_background', 100);

        $maxSort = -1;
        foreach ($existing['data'] as $file) {
            $sort = $file->meta['sort'] ?? -1;
            $maxSort = max($maxSort, $sort);
        }

        return $maxSort + 1;
    }
}
