<?php

namespace App\Models;

use App\Domain\Entities\Tenant as TenantEntity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tenant extends Model
{
    use HasUuids, HasFactory;

    protected $connection = 'landlord';

    protected $fillable = [
        'name',
        'subdomain',
        'database_name',
        'subscription_plan_id',
        'theme_primary_color',
        'theme_secondary_color',
        'logo_path',
        'status',
        'created_via',
        'broadcasting_provider_id',
        'storage_provider_id',
        'ai_provider_id',
        'backup_provider_id',
    ];

    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class);
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

    public function backupProvider(): BelongsTo
    {
        return $this->belongsTo(InfrastructureProvider::class, 'backup_provider_id');
    }

    public function toEntity(): TenantEntity
    {
        return new TenantEntity(
            id: $this->id,
            name: $this->name,
            subdomain: $this->subdomain,
            databaseName: $this->database_name,
            subscriptionPlanId: $this->subscription_plan_id,
            themePrimaryColor: $this->theme_primary_color,
            themeSecondaryColor: $this->theme_secondary_color,
            logoPath: $this->logo_path,
            status: $this->status,
            createdVia: $this->created_via,
            broadcastingProviderId: $this->broadcasting_provider_id,
            storageProviderId: $this->storage_provider_id,
            aiProviderId: $this->ai_provider_id,
            backupProviderId: $this->backup_provider_id,
            createdAt: $this->created_at,
            updatedAt: $this->updated_at,
        );
    }
}
