<?php

namespace App\Models;

use App\Domain\Entities\SubscriptionPlan as SubscriptionPlanEntity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'is_public',
        'tertiary_color',
        'icon_paths',
        'broadcasting_provider_id',
        'storage_provider_id',
        'ai_provider_id',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'limits' => 'array',
            'is_active' => 'boolean',
            'is_public' => 'boolean',
            'icon_paths' => 'array',
        ];
    }

    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }

    public function broadcastingProvider(): BelongsTo
    {
        return $this->belongsTo(InfrastructureProvider::class, 'broadcasting_provider_id');
    }

    public function storageProvider(): BelongsTo
    {
        return $this->belongsTo(InfrastructureProvider::class, 'storage_provider_id');
    }

    public function aiProvider(): BelongsTo
    {
        return $this->belongsTo(InfrastructureProvider::class, 'ai_provider_id');
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
            isPublic: $this->is_public,
            tertiaryColor: $this->tertiary_color,
            iconPaths: $this->icon_paths ?? [],
            broadcastingProviderId: $this->broadcasting_provider_id,
            storageProviderId: $this->storage_provider_id,
            aiProviderId: $this->ai_provider_id,
            createdAt: $this->created_at,
            updatedAt: $this->updated_at,
        );
    }
}
