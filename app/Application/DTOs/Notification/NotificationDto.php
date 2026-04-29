<?php

namespace App\Application\DTOs\Notification;

class NotificationDto
{
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly array $data,
        public readonly ?string $read_at,
        public readonly string $created_at,
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'data' => $this->data,
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
        ];
    }
}
