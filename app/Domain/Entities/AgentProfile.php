<?php

namespace App\Domain\Entities;

use DateTime;

class AgentProfile
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ?string $description,
        public readonly ?string $avatar,
        public readonly ?string $systemPrompt,
        public readonly ?string $model,
        public readonly bool $isActive = true,
        public readonly ?DateTime $createdAt = null,
        public readonly ?DateTime $updatedAt = null,
    ) {}
}
