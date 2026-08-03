<?php

namespace App\Domain\Entities;

use App\Application\DTOs\Audit\AuditLogDto;
use DateTime;

class AuditLog
{
    public function __construct(
        public readonly string $id,
        public readonly string $userId,
        public readonly string $userType,
        public readonly string $userName,
        public readonly string $action,
        public readonly string $modelType,
        public readonly ?string $modelId,
        public readonly ?array $oldValues,
        public readonly ?array $newValues,
        public readonly ?string $description,
        public readonly ?string $ipAddress,
        public readonly ?string $userAgent,
        public readonly ?string $url,
        public readonly ?string $method,
        public readonly ?array $tags,
        public readonly ?array $metadata,
        public readonly DateTime $createdAt
    ) {}

    /**
     * Converte a entidade em DTO
     */
    public function toDto(): AuditLogDto
    {
        return new AuditLogDto(
            id: $this->id,
            user: [
                'id' => $this->userId,
                'type' => $this->userType,
                'name' => $this->userName,
            ],
            action: $this->action,
            model: [
                'type' => $this->modelType,
                'id' => $this->modelId,
            ],
            changes: [
                'old' => $this->oldValues,
                'new' => $this->newValues,
            ],
            description: $this->description,
            context: [
                'ip' => $this->ipAddress,
                'user_agent' => $this->userAgent,
                'url' => $this->url,
                'method' => $this->method,
            ],
            tags: $this->tags,
            metadata: $this->metadata,
            created_at: $this->createdAt->format('Y-m-d H:i:s')
        );
    }

    /**
     * Verifica se é uma ação crítica
     */
    public function isCritical(): bool
    {
        return in_array('critical', $this->tags ?? []);
    }

    /**
     * Verifica se é relacionado a segurança
     */
    public function isSecurityRelated(): bool
    {
        return in_array('security', $this->tags ?? []);
    }

    /**
     * Verifica se tem mudanças
     */
    public function hasChanges(): bool
    {
        return !empty($this->oldValues) || !empty($this->newValues);
    }

    /**
     * Obtém o nome legível do modelo
     */
    public function getModelName(): string
    {
        return class_basename($this->modelType);
    }

    /**
     * Obtém o nome legível da ação
     */
    public function getActionName(): string
    {
        return match($this->action) {
            'created' => 'Criado',
            'updated' => 'Atualizado',
            'deleted' => 'Deletado',
            'viewed' => 'Visualizado',
            'login' => 'Login',
            'logout' => 'Logout',
            default => ucfirst($this->action),
        };
    }
}

