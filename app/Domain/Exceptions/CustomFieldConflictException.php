<?php

namespace App\Domain\Exceptions;

use Exception;

/**
 * Two administrators allocated the same custom-field `num` at once.
 *
 * `num` is the stable handle the storage column is named after and it is
 * never reused, so it is allocated as max(num) + 1 under a row lock. The
 * unique(host, num) index is the backstop; when it fires, the right answer
 * is 409 and "try again", not a 500 — the second save is legitimate, it just
 * lost a race by microseconds.
 */
class CustomFieldConflictException extends Exception
{
    public function __construct(string $message = 'Custom field definition conflict', int $code = 409, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
