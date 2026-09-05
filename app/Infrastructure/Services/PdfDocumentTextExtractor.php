<?php

namespace App\Infrastructure\Services;

use App\Domain\Exceptions\DocumentExtractionException;
use App\Domain\Services\DocumentTextExtractorInterface;
use Illuminate\Http\UploadedFile;
use Smalot\PdfParser\Config;
use Smalot\PdfParser\Parser;

/**
 * PDF and plain text, into searchable text.
 *
 * `mpdf` was already here and only *writes* PDFs, so this is the first thing in
 * the product that reads one.
 *
 * ## Why the mime list is here and not only in the FormRequest
 *
 * `UploadFileRequest` has no MIME allowlist at all and a hardcoded
 * `max:102400`, and the seeded `storage.allowed_mimes` is read nowhere. This
 * class does not fix that — it refuses to be the next place with the same hole.
 * The extractor is asked what it supports and the request validates against
 * that answer, so the two cannot drift.
 *
 * ## Scanned PDFs
 *
 * A scan is an image and yields nothing. That is returned as an empty string,
 * not an exception, and the caller is what turns it into a message the person
 * uploading can act on. Storing an empty document would leave an assistant
 * that confidently finds nothing in a manual the tenant believes it has read.
 */
class PdfDocumentTextExtractor implements DocumentTextExtractorInterface
{
    public function supportedMimeTypes(): array
    {
        return ['application/pdf', 'text/plain', 'text/markdown'];
    }

    public function supportedExtensions(): array
    {
        return ['pdf', 'txt', 'md'];
    }

    public function extract(UploadedFile $file): string
    {
        $mime = $file->getMimeType();

        if ($mime !== 'application/pdf') {
            return $this->normalise((string) file_get_contents($file->getRealPath()));
        }

        try {
            $text = (new Parser([], $this->parserConfig()))->parseFile($file->getRealPath())->getText();
        } catch (\Throwable $e) {
            // Encrypted, malformed, or not really a PDF. The message is shown
            // to a tenant admin, so it carries the parser's reason and nothing
            // about paths.
            throw DocumentExtractionException::unreadable($e->getMessage());
        }

        return $this->normalise($text);
    }

    /**
     * pdfparser defaults are wrong for a web request.
     *
     * `retainImageContent` is true by default, so every embedded image is held
     * in memory even though only `getText()` is ever called — a photo-heavy
     * PDF can be an order of magnitude larger in RAM than on disk. And
     * `decodeMemoryLimit` of 0 means a FlateDecode stream inflates without
     * bound. An OOM here is a FATAL error, which the try/catch above cannot
     * see, so it takes the php-fpm worker with it rather than answering 422.
     */
    private function parserConfig(): Config
    {
        $config = new Config();
        $config->setRetainImageContent(false);
        // 64 MB of decoded stream is far more than any text document needs and
        // well inside the worker's own memory_limit.
        $config->setDecodeMemoryLimit(64 * 1024 * 1024);

        return $config;
    }

    /**
     * PDF extraction produces ragged whitespace — a line break per rendered
     * line, and runs of spaces where the layout had columns. Left alone it
     * wastes the excerpt radius and makes a LIKE search miss phrases that are
     * split across a line.
     */
    private function normalise(string $text): string
    {
        // MySQL's utf8mb4 rejects invalid byte sequences; SQLite accepts them
        // happily. So a legacy .txt in ISO-8859-1 — entirely likely for the
        // Portuguese tenants this kit targets, where "até" is 61 74 e9 —
        // would 500 in production with a green test suite. That is the exact
        // trap CLAUDE.md records, so it is handled at the point the bytes
        // arrive rather than left for the column to discover.
        if (! mb_check_encoding($text, 'UTF-8')) {
            $detected = mb_detect_encoding($text, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true) ?: 'Windows-1252';
            $text = mb_convert_encoding($text, 'UTF-8', $detected);
        }

        // Anything still invalid (a truly mixed file, or a PDF that decoded to
        // rubbish) is scrubbed rather than allowed to reach the column.
        $text = preg_replace('/[^\P{C}\n\t]+/u', '', $text) ?? $text;

        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text;

        return trim($text);
    }
}
