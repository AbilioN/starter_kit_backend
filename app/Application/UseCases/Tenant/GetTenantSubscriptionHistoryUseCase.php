<?php

namespace App\Application\UseCases\Tenant;

use App\Application\DTOs\Tenant\SubscriptionPaymentDto;
use App\Domain\Entities\MockPayment;
use App\Domain\Repositories\MockPaymentRepositoryInterface;
use App\Domain\Repositories\SubscriptionPlanRepositoryInterface;
use App\Domain\Repositories\TenantRepositoryInterface;

/**
 * Histórico de subscrição visível ao tenant owner.
 *
 * Fonte: o ledger `mock_payments` (landlord). Cada linha traz
 * `metadata.trigger` (`signup` | `plan_change`), o que faz do ledger a própria
 * linha do tempo de mudanças de plano — inclusive as feitas pelo GodAdmin, que
 * passam pelo mesmo RecordMockPaymentUseCase.
 *
 * `landlord_audit_logs` NÃO é usado aqui de propósito: LogLandlordAuditUseCase
 * fixa actor_type='godadmin' mesmo quando quem age é o próprio tenant owner,
 * então uma coluna "alterado por" mentiria para o cliente.
 */
class GetTenantSubscriptionHistoryUseCase
{
    public function __construct(
        private MockPaymentRepositoryInterface $mockPaymentRepository,
        private SubscriptionPlanRepositoryInterface $subscriptionPlanRepository,
        private TenantRepositoryInterface $tenantRepository,
    ) {}

    public function execute(string $tenantId, int $page = 1, int $perPage = 15): array
    {
        $result = $this->mockPaymentRepository->findByTenantPaginated($tenantId, $page, $perPage);

        // Uma consulta só para o catálogo inteiro, indexada por id — evita N+1
        // sem precisar de join. O catálogo tem uma mão-cheia de linhas.
        $plansById = [];
        foreach ($this->subscriptionPlanRepository->findAll() as $plan) {
            $plansById[$plan->id] = $plan;
        }

        $data = array_map(
            fn (MockPayment $payment) => $this->toDto($payment, $plansById)->toArray(),
            $result['data'],
        );

        $tenant = $this->tenantRepository->findById($tenantId);
        $currentPlan = $tenant?->subscriptionPlanId ? ($plansById[$tenant->subscriptionPlanId] ?? null) : null;

        return [
            'success' => true,
            'data' => $data,
            'current_plan' => $currentPlan ? [
                'id' => $currentPlan->id,
                'name' => $currentPlan->name,
                'slug' => $currentPlan->slug,
                'price_cents' => $currentPlan->priceCents,
            ] : null,
            'pagination' => [
                'total' => $result['total'],
                'per_page' => $result['per_page'],
                'current_page' => $result['current_page'],
                'last_page' => $result['last_page'],
                'from' => $result['from'],
                'to' => $result['to'],
            ],
        ];
    }

    /** @param array<string, \App\Domain\Entities\SubscriptionPlan> $plansById */
    private function toDto(MockPayment $payment, array $plansById): SubscriptionPaymentDto
    {
        $plan = $payment->subscriptionPlanId ? ($plansById[$payment->subscriptionPlanId] ?? null) : null;

        return new SubscriptionPaymentDto(
            id: $payment->id,
            amount_cents: $payment->amountCents,
            status: $payment->status,
            trigger: $payment->metadata['trigger'] ?? null,
            plan_id: $payment->subscriptionPlanId,
            plan_slug: $payment->metadata['plan_slug'] ?? $plan?->slug,
            plan_name: $plan?->name,
            created_at: $payment->createdAt?->format('Y-m-d H:i:s'),
        );
    }
}
