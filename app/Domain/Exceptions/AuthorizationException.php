<?php

namespace App\Domain\Exceptions;

use Exception;

class AuthorizationException extends Exception
{
    public function __construct(string $message = "Unauthorized access", int $code = 403, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
