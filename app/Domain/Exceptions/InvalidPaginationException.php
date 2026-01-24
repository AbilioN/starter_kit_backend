<?php

namespace App\Domain\Exceptions;

use Exception;

class InvalidPaginationException extends Exception
{
    public function __construct(string $message = "Invalid pagination parameters", int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public static function invalidPage(int $page): self
    {
        return new self("Page number must be greater than 0, got {$page}");
    }

    public static function invalidPerPage(int $perPage): self
    {
        return new self("Items per page must be between 1 and 100, got {$perPage}");
    }

    public static function invalidRange(int $page, int $perPage): self
    {
        return new self("Invalid pagination range: page {$page}, per page {$perPage}");
    }
}
