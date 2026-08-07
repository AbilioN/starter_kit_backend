<?php

namespace App\Domain\Exceptions;

use Exception;

/**
 * Thrown by PlaceholderResolverService when a `{field!}` (strict) merge
 * field resolves to an empty value — aborts the whole generation rather
 * than shipping a document with a blank name.
 */
class StrictFieldEmptyException extends Exception
{
    public function __construct(string $fieldLabel, int $code = 422, ?Exception $previous = null)
    {
        parent::__construct("The required field '{$fieldLabel}' is not filled in for this record.", $code, $previous);
    }
}
