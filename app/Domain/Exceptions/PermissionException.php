<?php

namespace App\Domain\Exceptions;

use Exception;

class PermissionException extends Exception
{
    public function __construct(string $message = "Permission not found")
    {
        parent::__construct($message);
        
        
    }
}