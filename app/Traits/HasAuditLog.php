<?php

namespace App\Traits;

use App\Application\UseCases\Audit\LogAuditUseCase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

trait HasAuditLog
{
    /**
     * Boot do trait - registra eventos do model
     */
    protected static function bootHasAuditLog(): void
    {
        // Ao criar
        static::created(function ($model) {
            $model->logAudit('created', null, $model->getAttributes());
        });

        // Ao atualizar
        static::updated(function ($model) {
            $model->logAudit('updated', $model->getOriginal(), $model->getChanges());
        });

        // Ao deletar
        static::deleted(function ($model) {
            $model->logAudit('deleted', $model->getAttributes(), null);
        });
    }

    /**
     * Registra log de auditoria
     */
    protected function logAudit(
        string $action,
        ?array $oldValues = null,
        ?array $newValues = null
    ): void {
        // Remove atributos $hidden antes de gravar. Sem isto, uma troca de senha
        // escrevia os hashes bcrypt antigo E novo em audit_logs.old_values /
        // new_values — a auditoria é imutável por design, logo esses hashes
        // ficariam lá para sempre. Vale para todo modelo que usa o trait
        // (password, remember_token, tokens de API, etc).
        $hidden = array_flip($this->getHidden());
        $oldValues = $oldValues !== null ? array_diff_key($oldValues, $hidden) : null;
        $newValues = $newValues !== null ? array_diff_key($newValues, $hidden) : null;

        try {
            $user = Auth::user();
            
            if (!$user) {
                return; // Não registra se não houver usuário autenticado
            }

            // Verificar se o user tem método getType (ChatUser interface)
            $userType = method_exists($user, 'getType') 
                ? $user->getType() 
                : (is_a($user, \App\Models\Admin::class) ? 'admin' : 'user');
            
            $userId = method_exists($user, 'getId') 
                ? $user->getId() 
                : $user->id;

            $useCase = App::make(LogAuditUseCase::class);
            
            $description = $this->getAuditDescription($action);
            $tags = $this->getAuditTags($action);

            $useCase->execute(
                userId: $userId,
                userType: ucfirst($userType),
                action: $action,
                modelType: get_class($this),
                modelId: $this->id ?? null,
                oldValues: $oldValues,
                newValues: $newValues,
                description: $description,
                tags: $tags
            );
        } catch (\Exception $e) {
            // Não quebra a aplicação se houver erro no audit
            // Log do erro mas não interrompe o fluxo
            Log::warning('Audit log failed: ' . $e->getMessage());
        }
    }

    /**
     * Gera descrição legível para o audit log
     */
    protected function getAuditDescription(string $action): string
    {
        $modelName = class_basename($this);
        $identifier = $this->getModelIdentifier();

        return match($action) {
            'created' => "{$modelName} '{$identifier}' foi criado",
            'updated' => "{$modelName} '{$identifier}' foi atualizado",
            'deleted' => "{$modelName} '{$identifier}' foi deletado",
            default => "{$modelName} '{$identifier}' - {$action}",
        };
    }

    /**
     * Obtém identificador do modelo (name, email, id)
     */
    protected function getModelIdentifier(): string
    {
        if (isset($this->name)) {
            return $this->name;
        }

        if (isset($this->email)) {
            return $this->email;
        }

        if (isset($this->slug)) {
            return $this->slug;
        }

        return $this->id ?? 'N/A';
    }

    /**
     * Define tags para o audit log
     */
    protected function getAuditTags(string $action): array
    {
        $tags = [];

        // Tag de segurança para ações críticas
        if (in_array($action, ['deleted', 'updated'])) {
            $tags[] = 'security';
        }

        // Tag crítica para deletar
        if ($action === 'deleted') {
            $tags[] = 'critical';
        }

        return $tags;
    }

    /**
     * Registra audit log manualmente
     */
    public function audit(string $action, ?array $oldValues = null, ?array $newValues = null, ?array $tags = null): void
    {
        $this->logAudit($action, $oldValues, $newValues);
    }
}

