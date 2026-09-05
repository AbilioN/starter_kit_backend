<?php

namespace App\Models;

use App\Domain\Entities\Setting as SettingEntity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasUuids;

    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'label',
        'description',
        'is_public',
    ];

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
        ];
    }

    public function toEntity(): SettingEntity
    {
        return new SettingEntity(
            id: $this->id,
            key: $this->key,
            value: $this->castValue($this->value, $this->type),
            // The column is nullable and the entity is not. Every write
            // through SettingRepository stringifies, so this only bites on a
            // direct insert — which a migration did on 2026-09-06, taking the
            // whole settings list down with a TypeError.
            rawValue: $this->value ?? '',
            type: $this->type,
            group: $this->group,
            label: $this->label,
            description: $this->description,
            isPublic: $this->is_public,
            createdAt: $this->created_at,
            updatedAt: $this->updated_at,
        );
    }

    private function castValue(?string $raw, string $type): mixed
    {
        if ($raw === null) {
            return null;
        }

        return match ($type) {
            'boolean' => filter_var($raw, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $raw,
            'array', 'json' => json_decode($raw, true),
            default => $raw,
        };
    }
}
