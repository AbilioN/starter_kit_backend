<?php

namespace App\Domain\Exceptions;

use Exception;

class RegistrationException extends Exception
{
    public function __construct(string $message = 'Registration failed')
    {
        parent::__construct($message);
    }
} 