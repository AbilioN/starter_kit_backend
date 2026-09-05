<?php

namespace App\Domain\Exceptions;

use RuntimeException;

/**
 * The file could not be turned into text.
 *
 * Distinct from "the file had no text in it", which is not an error and is a
 * scanned PDF — a real and common case that must be reported to the person
 * uploading rather than stored as an empty document that silently answers
 * nothing.
 */
class DocumentExtractionException extends RuntimeException
{
    public static function unreadable(string $reason): self
    {
        return new self("That file could not be read: {$reason}");
    }
}
