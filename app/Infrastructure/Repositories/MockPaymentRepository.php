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

    public function findByTenantPaginated(string $tenantId, int $page = 1, int $perPage = 15): array
    {
        $paginator = MockPaymentModel::where('tenant_id', $tenantId)
            ->orderByDesc('created_at')
            // Desempate por id: `created_at` é `timestamp` sem sub-segundo, e o
            // signup grava plano + pagamento no mesmo segundo — sem isto a ordem
            // entre linhas simultâneas é indefinida e varia entre páginas.
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);

        return [
            'data' => array_map(fn (MockPaymentModel $p) => $p->toEntity(), $paginator->items()),
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ];
    }
}
