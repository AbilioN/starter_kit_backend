<?php

namespace App\Models;

use App\Domain\Entities\SubscriptionPlan as SubscriptionPlanEntity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    use HasUuids, HasFactory;

    protected $connection = 'landlord';

    protected $fillable = [
        'name',
        'slug',
        'price_cents',
        'features',
        'limits',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'limits' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }

    public function toEntity(): SubscriptionPlanEntity
    {
        return new SubscriptionPlanEntity(
            id: $this->id,
            name: $this->name,
            slug: $this->slug,
            priceCents: $this->price_cents,
            features: $this->features ?? [],
            limits: $this->limits ?? [],
            isActive: $this->is_active,
            createdAt: $this->created_at,
            updatedAt: $this->updated_at,
        );
    }
}
