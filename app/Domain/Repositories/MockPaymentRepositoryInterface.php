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

    /**
     * Pagamentos de UM tenant, mais recentes primeiro.
     *
     * @return array{data: MockPayment[], total: int, per_page: int, current_page: int, last_page: int, from: ?int, to: ?int}
     */
    public function findByTenantPaginated(string $tenantId, int $page = 1, int $perPage = 15): array;
}
