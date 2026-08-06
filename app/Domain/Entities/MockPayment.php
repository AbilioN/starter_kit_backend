<?php

namespace App\Domain\Entities;

use DateTime;

class MockPayment
{
    public function __construct(
        public readonly string $id,
        public readonly string $tenantId,
        public readonly ?string $subscriptionPlanId,
        public readonly int $amountCents,
        public readonly string $status,
        public readonly ?array $metadata = null,
        public readonly ?DateTime $createdAt = null,
    ) {}
}
