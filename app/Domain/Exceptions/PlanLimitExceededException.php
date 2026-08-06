<?php

namespace App\Domain\Exceptions;

use Exception;

class PlanLimitExceededException extends Exception
{
    public function __construct(string $message = "Plan limit exceeded", int $code = 402, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
