<?php

namespace App\Application\DTOs\Audit;

class AuditLogDto
{
    public function __construct(
        public readonly string $id,
        public readonly array $user,
        public readonly string $action,
        public readonly array $model,
        public readonly array $changes,
        public readonly ?string $description,
        public readonly array $context,
        public readonly ?array $tags,
        public readonly ?array $metadata,
        public readonly string $created_at
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user' => $this->user,
            'action' => $this->action,
            'model' => $this->model,
            'changes' => $this->changes,
            'description' => $this->description,
            'context' => $this->context,
            'tags' => $this->tags,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at,
        ];
    }
}

