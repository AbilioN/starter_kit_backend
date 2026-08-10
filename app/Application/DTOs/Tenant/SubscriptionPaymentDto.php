<?php

namespace App\Application\DTOs\Tenant;

class SubscriptionPaymentDto
{
    public function __construct(
        public readonly string $id,
        public readonly int $amount_cents,
        public readonly string $status,
        /** signup | plan_change — vem de metadata.trigger, gravado por RecordMockPaymentUseCase */
        public readonly ?string $trigger,
        /** null quando o plano foi apagado (a FK é nullOnDelete) */
        public readonly ?string $plan_id,
        /** Slug capturado NO MOMENTO do pagamento: rótulo historicamente correto
         *  mesmo que o plano tenha sido renomeado desde então. */
        public readonly ?string $plan_slug,
        /** Nome atual do plano; null se apagado — nesse caso a UI cai no slug. */
        public readonly ?string $plan_name,
        public readonly ?string $created_at,
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'amount_cents' => $this->amount_cents,
            'status' => $this->status,
            'trigger' => $this->trigger,
            'plan_id' => $this->plan_id,
            'plan_slug' => $this->plan_slug,
            'plan_name' => $this->plan_name,
            'created_at' => $this->created_at,
        ];
    }
}
