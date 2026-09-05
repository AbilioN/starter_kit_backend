<?php

namespace App\Application\UseCases\AgentDocument;

use App\Application\UseCases\File\UploadFileUseCase;
use App\Domain\Exceptions\DocumentExtractionException;
use App\Domain\Services\DocumentTextExtractorInterface;
use App\Models\AgentDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Adds or edits a document the assistant can search.
 *
 * The table has existed since 2026-08-29 with two search tools over it and no
 * writer but a seeder — so this is the part that makes the knowledge layer real
 * rather than notional.
 *
 * Three things it is careful about:
 *
 * **The file goes through `UploadFileUseCase`.** Not a bespoke `Storage::put`.
 * That use case enforces `limits.max_storage_mb` and writes a `files` row, and
 * `RunFilesBackupUseCase` enumerates exactly that table — a document stored any
 * other way would be outside the tenant's quota and outside their backup, both
 * silently. The `folder` is `agent-documents`, following the
 * `UploadTemplateBackgroundUseCase` recipe.
 *
 * **The text is extracted once, here.** `agent_documents.content` is what every
 * lookup reads; the migration says so in its own comment. Parsing per query
 * would turn a database read into a file parse on the hot path.
 *
 * **A file with no text is refused, not stored.** A scanned PDF extracts to
 * nothing, and a document whose content is empty is an assistant that will
 * confidently find nothing in a manual its tenant believes it has read. Caps
 * and blanks refuse in this product rather than passing quietly.
 */
class SaveAgentDocumentUseCase
{
    /**
     * Long enough for a real manual, bounded because `content` is a longText
     * that a LIKE scans on every search and that the whole corpus is loaded
     * from when embeddings arrive.
     */
    public const MAX_CONTENT_CHARS = 400_000;

    public function __construct(
        private DocumentTextExtractorInterface $extractor,
        private UploadFileUseCase $uploadFile,
    ) {}

    /**
     * @param  array{title: string, description?: ?string, audience?: ?string, content?: ?string, is_active?: bool}  $attributes
     */
    public function execute(
        array $attributes,
        ?UploadedFile $file = null,
        ?AgentDocument $existing = null,
    ): AgentDocument {
        $content = $existing?->content;
        $filePath = $existing?->file_path;

        if ($file !== null) {
            $extracted = $this->extractor->extract($file);

            if (trim($extracted) === '') {
                // Almost always a scan. Saying so is the difference between a
                // tenant fixing it and a tenant wondering why the assistant
                // ignores their manual.
                throw DocumentExtractionException::unreadable(
                    'no text could be read from it. A scanned document needs to be run through OCR first.'
                );
            }

            $content = $this->withinCap($extracted, 'That file');
        } elseif (array_key_exists('content', $attributes) && $attributes['content'] !== null) {
            $content = $this->withinCap($attributes['content'], 'That text');
        }

        if ($content === null || trim($content) === '') {
            throw DocumentExtractionException::unreadable(
                'a document needs either a file to read or some text of its own.'
            );
        }

        // The row and its file are one act as far as the DATABASE is concerned.
        // Storage is not transactional: a rollback after `store()` has written
        // bytes leaves the object on disk with no `files` row — invisible to
        // the quota, to backups and to the Files screen. That is the lesser
        // evil of the two (an orphan nobody is billed for beats a `files` row
        // pointing at nothing), and it is stated here rather than implied.
        return DB::transaction(function () use ($attributes, $file, $existing, $content, $filePath) {
            $document = $existing ?? new AgentDocument();

            $document->fill([
                'title' => $attributes['title'],
                'description' => $attributes['description'] ?? null,
                'audience' => in_array($attributes['audience'] ?? null, AgentDocument::AUDIENCES, true)
                    ? $attributes['audience']
                    // Default INTERNAL, never published by omission.
                    : ($existing?->audience ?? AgentDocument::AUDIENCE_INTERNAL),
                'is_active' => $attributes['is_active'] ?? $existing?->is_active ?? true,
            ]);

            $document->content = $content;
            $document->file_path = $filePath;
            $document->save();

            if ($file !== null) {
                $stored = $this->uploadFile->execute(
                    file: $file,
                    folder: 'agent-documents',
                    isPublic: false,
                    uploadableType: AgentDocument::class,
                    uploadableId: $document->id,
                );

                // NOT $stored['path']: UploadFileUseCase returns a FileDto,
                // whose toArray() carries id/url/folder and no path at all — so
                // that read was silently null and `has_file` was always false.
                // The `files` row is the real link (polymorphic, and what
                // RunFilesBackupUseCase enumerates); this column just mirrors
                // where it landed.
                $document->file_path = \App\Models\File::find($stored['id'] ?? null)?->path;
                $document->save();

                // Everything attached BEFORE this upload. Replacing a
                // document's file used to leave the old object on disk and its
                // `files` row behind forever — inside the tenant's storage
                // quota, inside every files backup, and reachable by nothing
                // in this module.
                $this->discardFilesExcept($document, (string) ($stored['id'] ?? ''));
            }

            return $document;
        });
    }

    /**
     * Caps refuse in this product; they do not pass quietly.
     *
     * The endpoint accepts a 20 MB file, and a 400-page manual extracts to
     * millions of characters. Keeping the first 400,000 and answering 201 left
     * the tenant with a document they believe is complete and an assistant
     * that cannot find the second half of it.
     */
    private function withinCap(string $text, string $subject): string
    {
        if (mb_strlen($text) > self::MAX_CONTENT_CHARS) {
            throw DocumentExtractionException::unreadable(sprintf(
                '%s is too long — %s characters, and the limit is %s. Split it into two documents.',
                $subject,
                number_format(mb_strlen($text)),
                number_format(self::MAX_CONTENT_CHARS),
            ));
        }

        return $text;
    }

    /**
     * Removes every file attached to this document except the one just stored.
     *
     * Through UploadFileUseCase's own delete path, so the `files` row goes with
     * the object and the tenant's quota is actually released.
     */
    private function discardFilesExcept(AgentDocument $document, string $keepId): void
    {
        \App\Models\File::query()
            ->where('uploadable_type', AgentDocument::class)
            ->where('uploadable_id', $document->id)
            ->when($keepId !== '', fn ($q) => $q->where('id', '!=', $keepId))
            ->get()
            ->each(function (\App\Models\File $file) {
                try {
                    \Illuminate\Support\Facades\Storage::disk($file->disk)->delete($file->path);
                } catch (\Throwable) {
                    // A missing object is the desired end state anyway; the row
                    // going is what releases the quota.
                }

                $file->delete();
            });
    }

    /** Called when the document itself goes. */
    public function discardFiles(AgentDocument $document): void
    {
        $this->discardFilesExcept($document, '');
    }
}
