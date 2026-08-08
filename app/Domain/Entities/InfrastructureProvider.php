<?php

namespace App\Domain\Entities;

use DateTime;

class InfrastructureProvider
{
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly string $name,
        public readonly array $config,
        public readonly bool $isActive = true,
        public readonly ?DateTime $createdAt = null,
        public readonly ?DateTime $updatedAt = null,
    ) {}
}
