<?php

namespace App\Domain\Exceptions;

use Exception;

class AuthenticationException extends Exception
{
    public function __construct(string $message = 'Invalid credentials')
    {
        parent::__construct($message);
    }
}
