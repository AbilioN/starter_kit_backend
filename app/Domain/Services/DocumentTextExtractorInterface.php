<?php

namespace App\Domain\Services;

use Illuminate\Http\UploadedFile;

/**
 * Turns an uploaded file into the text the assistant will search.
 *
 * An interface, and not because a second implementation is planned: extraction
 * is the one part of the document pipeline that touches an untrusted binary,
 * so being able to hand a use-case test a deterministic fake — rather than a
 * fixture PDF and a parser's mood — is what keeps the tests about the use case.
 *
 * The extracted text is written once, on ingestion, into
 * `agent_documents.content`. That is deliberate and is stated in the table's
 * own migration: a lookup must be a database read, never a file parse.
 */
interface DocumentTextExtractorInterface
{
    /**
     * @return string the document's text, or '' when there is none to be had
     *
     * @throws \App\Domain\Exceptions\DocumentExtractionException when the file
     *                                                           cannot be read at all
     */
    public function extract(UploadedFile $file): string;

    /** Mime types this extractor will accept. */
    public function supportedMimeTypes(): array;

    /**
     * Extensions this extractor will accept.
     *
     * Both, not either. A `.php` file whose body is prose is detected as
     * `text/plain` and would pass a mime check alone; an extension check alone
     * is trivially renamed around.
     */
    public function supportedExtensions(): array;
}
