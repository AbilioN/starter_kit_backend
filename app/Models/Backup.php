<?php

namespace App\Models;

use App\Domain\Entities\Backup as BackupEntity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Backup extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'landlord';

    protected $fillable = [
        'tenant_id',
        'kind',
        'status',
        'provider_id',
        'disk_name',
        'destination_path',
        'size_bytes',
        'checksum',
        'is_encrypted',
        'started_at',
        'finished_at',
        'pruned_at',
        'error',
        'request_id',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'is_encrypted' => 'boolean',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'pruned_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(InfrastructureProvider::class, 'provider_id');
    }

    public function toEntity(): BackupEntity
    {
        return new BackupEntity(
            id: $this->id,
            tenantId: $this->tenant_id,
            kind: $this->kind,
            status: $this->status,
            providerId: $this->provider_id,
            diskName: $this->disk_name,
            destinationPath: $this->destination_path,
            sizeBytes: $this->size_bytes,
            checksum: $this->checksum,
            // Cast, not passthrough: a freshly created row has not read the
            // column's DB default back, so the attribute is still null here.
            isEncrypted: (bool) $this->is_encrypted,
            startedAt: $this->started_at,
            finishedAt: $this->finished_at,
            prunedAt: $this->pruned_at,
            error: $this->error,
            requestId: $this->request_id,
        );
    }
}
