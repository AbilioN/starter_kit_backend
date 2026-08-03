<?php

namespace App\Domain\Entities;

use DateTime;

class SubscriptionPlan
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $slug,
        public readonly ?int $priceCents,
        public readonly array $features,
        public readonly array $limits,
        public readonly bool $isActive = true,
        public readonly ?DateTime $createdAt = null,
        public readonly ?DateTime $updatedAt = null
    ) {}
}
