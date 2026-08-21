<?php

namespace App\Models;

use App\Domain\Entities\InfrastructureProvider as InfrastructureProviderEntity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InfrastructureProvider extends Model
{
    use HasUuids, HasFactory;

    protected $connection = 'landlord';

    protected $fillable = [
        'type',
        'name',
        'config',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            // Encrypted, not plain JSON: this column holds every tenant's
            // Pusher secret, S3 secret and BYOK provider key, and backups (5.3)
            // copy the landlord off-host by design. Existing rows are converted
            // by the 2026_08_21_000003 migration, which must run with this code
            // rather than after it — the cast cannot read a plaintext row.
            'config' => 'encrypted:array',
            'is_active' => 'boolean',
        ];
    }

    public function toEntity(): InfrastructureProviderEntity
    {
        return new InfrastructureProviderEntity(
            id: $this->id,
            type: $this->type,
            name: $this->name,
            config: $this->config ?? [],
            isActive: $this->is_active,
            createdAt: $this->created_at,
            updatedAt: $this->updated_at,
        );
    }
}
