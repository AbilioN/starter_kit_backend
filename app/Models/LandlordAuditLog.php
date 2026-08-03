<?php

namespace App\Models;

use App\Domain\Entities\LandlordAuditLog as LandlordAuditLogEntity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LandlordAuditLog extends Model
{
    use HasUuids, HasFactory;

    protected $connection = 'landlord';

    public $timestamps = false;

    protected $fillable = [
        'actor_type',
        'actor_id',
        'action',
        'model',
        'model_id',
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

    public function toEntity(): LandlordAuditLogEntity
    {
        return new LandlordAuditLogEntity(
            id: $this->id,
            actorType: $this->actor_type,
            actorId: $this->actor_id,
            action: $this->action,
            model: $this->model,
            modelId: $this->model_id,
            metadata: $this->metadata,
            createdAt: $this->created_at,
        );
    }
}
