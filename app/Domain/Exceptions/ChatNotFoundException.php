<?php

namespace App\Domain\Exceptions;

use Exception;

class ChatNotFoundException extends Exception
{
    public function __construct(string $message = "Chat not found", int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public static function forUser(int $userId, string $userType): self
    {
        return new self("No chats found for user ID {$userId} of type {$userType}");
    }

    public static function withId(int $chatId): self
    {
        return new self("Chat with ID {$chatId} not found");
    }
}
