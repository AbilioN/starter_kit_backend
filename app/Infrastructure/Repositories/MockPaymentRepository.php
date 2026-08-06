<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Entities\MockPayment;
use App\Domain\Repositories\MockPaymentRepositoryInterface;
use App\Models\MockPayment as MockPaymentModel;

class MockPaymentRepository implements MockPaymentRepositoryInterface
{
    public function record(
        string $tenantId,
        ?string $subscriptionPlanId,
        int $amountCents,
        ?array $metadata = null,
        string $status = 'succeeded'
    ): MockPayment {
        $payment = MockPaymentModel::create([
            'tenant_id' => $tenantId,
            'subscription_plan_id' => $subscriptionPlanId,
            'amount_cents' => $amountCents,
            'status' => $status,
            'metadata' => $metadata,
        ]);

        return $payment->toEntity();
    }
}
