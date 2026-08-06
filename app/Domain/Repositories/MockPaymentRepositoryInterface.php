<?php

namespace App\Domain\Repositories;

use App\Domain\Entities\MockPayment;

interface MockPaymentRepositoryInterface
{
    public function record(
        string $tenantId,
        ?string $subscriptionPlanId,
        int $amountCents,
        ?array $metadata = null,
        string $status = 'succeeded'
    ): MockPayment;
}
