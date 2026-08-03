<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Domain\Entities\AuditLog as AuditLogEntity;
use DateTime;

class AuditLog extends Model
{
    use HasUuids, HasFactory;

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'user_type',
        'user_name',
        'action',
        'model_type',
        'model_id',
        'old_values',
        'new_values',
        'description',
        'ip_address',
        'user_agent',
        'url',
        'method',
        'tags',
        'metadata',
        'created_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'tags' => 'array',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Converte o Model para Domain Entity
     */
    public function toEntity(): AuditLogEntity
    {
        return new AuditLogEntity(
            id: $this->id,
            userId: $this->user_id,
            userType: $this->user_type,
            userName: $this->user_name ?? '',
            action: $this->action,
            modelType: $this->model_type,
            modelId: $this->model_id,
            oldValues: $this->old_values,
            newValues: $this->new_values,
            description: $this->description,
            ipAddress: $this->ip_address,
            userAgent: $this->user_agent,
            url: $this->url,
            method: $this->method,
            tags: $this->tags,
            metadata: $this->metadata,
            createdAt: $this->created_at ?? new DateTime()
        );
    }

    /**
     * Scope para filtrar por usuário
     */
    public function scopeForUser($query, string $userId, string $userType)
    {
        return $query->where('user_id', $userId)
                    ->where('user_type', $userType);
    }

    /**
     * Scope para filtrar por modelo
     */
    public function scopeForModel($query, string $modelType, ?string $modelId = null)
    {
        $query->where('model_type', $modelType);
        
        if ($modelId !== null) {
            $query->where('model_id', $modelId);
        }
        
        return $query;
    }

    /**
     * Scope para filtrar por ação
     */
    public function scopeForAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope para filtrar por tags
     */
    public function scopeWithTag($query, string $tag)
    {
        return $query->whereJsonContains('tags', $tag);
    }

    /**
     * Scope para filtrar por data
     */
    public function scopeDateRange($query, ?string $from = null, ?string $to = null)
    {
        if ($from) {
            $query->where('created_at', '>=', $from);
        }
        
        if ($to) {
            $query->where('created_at', '<=', $to);
        }
        
        return $query;
    }
}

