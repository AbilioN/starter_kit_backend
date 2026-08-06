<?php

namespace App\Application\UseCases\Landlord;

use App\Domain\Entities\MockPayment;
use App\Domain\Repositories\MockPaymentRepositoryInterface;
use App\Domain\Repositories\SubscriptionPlanRepositoryInterface;

class RecordMockPaymentUseCase
{
    /**
     * No real gateway is involved - this always "succeeds" and simply
     * records the plan's price_cents as a ledger entry, so the financial
     * report has history to aggregate instead of only a live snapshot.
     * Audited on the landlord side with the same nil-UUID actor sentinel
     * RetrySettingsSyncJob::failed() uses, since neither a signup nor a
     * tenant-owner-driven plan change has a GodAdmin actor behind it.
     */
    private const SYSTEM_ACTOR_ID = '00000000-0000-0000-0000-000000000000';

    public function __construct(
        private MockPaymentRepositoryInterface $mockPaymentRepository,
        private SubscriptionPlanRepositoryInterface $subscriptionPlanRepository,
        private LogLandlordAuditUseCase $logLandlordAudit,
    ) {}

    public function execute(string $tenantId, ?string $subscriptionPlanId, string $trigger): ?MockPayment
    {
        if (! $subscriptionPlanId) {
            return null;
        }

        $plan = $this->subscriptionPlanRepository->findById($subscriptionPlanId);

        if (! $plan) {
            return null;
        }

        $payment = $this->mockPaymentRepository->record(
            tenantId: $tenantId,
            subscriptionPlanId: $subscriptionPlanId,
            amountCents: $plan->priceCents ?? 0,
            metadata: ['trigger' => $trigger, 'plan_slug' => $plan->slug],
        );

        $this->logLandlordAudit->execute(
            actorId: self::SYSTEM_ACTOR_ID,
            action: 'mock_payment_recorded',
            model: 'Tenant',
            modelId: $tenantId,
            metadata: ['subscription_plan_id' => $subscriptionPlanId, 'amount_cents' => $payment->amountCents, 'trigger' => $trigger],
        );

        return $payment;
    }
}
