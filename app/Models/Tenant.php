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
    ];

    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class);
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
            createdAt: $this->created_at,
            updatedAt: $this->updated_at,
        );
    }
}
