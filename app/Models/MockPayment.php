<?php

namespace App\Models;

use App\Domain\Entities\MockPayment as MockPaymentEntity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MockPayment extends Model
{
    use HasUuids;

    protected $connection = 'landlord';

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'subscription_plan_id',
        'amount_cents',
        'status',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }

    public function toEntity(): MockPaymentEntity
    {
        return new MockPaymentEntity(
            id: $this->id,
            tenantId: $this->tenant_id,
            subscriptionPlanId: $this->subscription_plan_id,
            amountCents: $this->amount_cents,
            status: $this->status,
            metadata: $this->metadata,
            createdAt: $this->created_at,
        );
    }
}
