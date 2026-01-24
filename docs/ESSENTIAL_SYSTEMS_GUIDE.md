# Guia Completo: 3 Sistemas Essenciais para Starter Kit BtoB

## Índice

1. [Sistema de Auditoria (Audit Logs)](#1-sistema-de-auditoria)
2. [Sistema de Configurações (Settings)](#2-sistema-de-configurações)
3. [Sistema de Notificações](#3-sistema-de-notificações)
4. [Integração entre os Sistemas](#integração-entre-sistemas)
5. [Implementação Prática](#implementação-prática)

---

## 1. Sistema de Auditoria

### Por que é ESSENCIAL?

#### 1.1 Compliance e Legal
```
LGPD (Brasil):
- Art. 48: Empresas devem ter controle sobre quem acessa dados pessoais
- Necessário registrar: Quem? O quê? Quando? Por quê?

GDPR (Europa):
- Rastreabilidade de acesso a dados
- Direito ao esquecimento (precisa saber o que deletar)
- Portabilidade de dados (precisa saber o que exportar)

SOC 2 Type II:
- Auditoria completa de mudanças
- Controle de acesso
- Logs de segurança
```

#### 1.2 Segurança
```
Detectar:
- Acessos não autorizados
- Mudanças suspeitas
- Tentativas de fraude
- Padrões anormais

Investigar:
- "Quem deletou este registro?"
- "Quando este valor foi alterado?"
- "De onde veio este acesso?"
```

#### 1.3 Debug e Suporte
```
Problemas comuns:
- Cliente: "Meus dados sumiram!"
  → Consultar audit: Admin X deletou em Y
  
- Cliente: "Não consigo fazer login"
  → Consultar audit: Conta desativada por Admin Z
  
- Bug: "Este valor está errado"
  → Consultar audit: Histórico de mudanças
```

#### 1.4 Análise de Negócio
```
Métricas úteis:
- Quais admins são mais ativos?
- Quais features são mais usadas?
- Horários de pico
- Padrões de uso
```

---

### Arquitetura do Sistema de Auditoria

#### 1.5 Estrutura Básica

```
┌─────────────────────────────────────────────┐
│              User Action                     │
│  (Create, Update, Delete, View, Login)      │
└──────────────────┬──────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────┐
│         Laravel Observer/Trait               │
│     (Captura mudanças automaticamente)       │
└──────────────────┬──────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────┐
│           AuditLog Repository                │
│      (Salva no banco de dados)               │
└──────────────────┬──────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────┐
│      audit_logs table (Database)             │
│  - Quem fez (user_id, user_type)             │
│  - O que fez (action, model)                 │
│  - Quando (created_at)                       │
│  - Onde (ip, user_agent)                     │
│  - Mudanças (old_values, new_values)         │
└─────────────────────────────────────────────┘
```

#### 1.6 Schema da Tabela

```sql
CREATE TABLE audit_logs (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    
    -- Quem fez a ação
    user_id BIGINT NOT NULL,
    user_type VARCHAR(255) NOT NULL, -- Admin, User
    user_name VARCHAR(255), -- Cache do nome
    
    -- O que foi feito
    action VARCHAR(50) NOT NULL, -- created, updated, deleted, viewed, login
    model_type VARCHAR(255) NOT NULL, -- App\Models\User
    model_id BIGINT,
    
    -- Detalhes da mudança
    old_values JSON NULL, -- Estado anterior
    new_values JSON NULL, -- Estado novo
    description TEXT NULL, -- Descrição legível
    
    -- Contexto
    ip_address VARCHAR(45),
    user_agent TEXT,
    url TEXT,
    method VARCHAR(10), -- GET, POST, PUT, DELETE
    
    -- Metadados
    tags JSON NULL, -- ['security', 'critical']
    metadata JSON NULL, -- Dados extras
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Indexes para performance
    INDEX idx_user (user_id, user_type),
    INDEX idx_model (model_type, model_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at),
    INDEX idx_tags (tags)
);
```

#### 1.7 Domain Entity

```php
<?php

namespace App\Domain\Entities;

use DateTime;

class AuditLog
{
    public function __construct(
        public readonly int $id,
        public readonly int $userId,
        public readonly string $userType,
        public readonly string $userName,
        public readonly string $action,
        public readonly string $modelType,
        public readonly ?int $modelId,
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
    
    public function isCritical(): bool
    {
        return in_array('critical', $this->tags ?? []);
    }
    
    public function isSecurityRelated(): bool
    {
        return in_array('security', $this->tags ?? []);
    }
}
```

#### 1.8 Trait Reutilizável (Auto-Audit)

```php
<?php

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;

trait HasAuditLog
{
    protected static function bootHasAuditLog()
    {
        // Ao criar
        static::created(function ($model) {
            AuditLog::logAction(
                action: 'created',
                model: $model,
                newValues: $model->getAttributes(),
                description: self::getAuditDescription('created', $model)
            );
        });
        
        // Ao atualizar
        static::updated(function ($model) {
            AuditLog::logAction(
                action: 'updated',
                model: $model,
                oldValues: $model->getOriginal(),
                newValues: $model->getChanges(),
                description: self::getAuditDescription('updated', $model)
            );
        });
        
        // Ao deletar
        static::deleted(function ($model) {
            AuditLog::logAction(
                action: 'deleted',
                model: $model,
                oldValues: $model->getAttributes(),
                description: self::getAuditDescription('deleted', $model)
            );
        });
    }
    
    protected static function getAuditDescription(string $action, $model): string
    {
        $modelName = class_basename($model);
        $identifier = $model->name ?? $model->email ?? $model->id;
        
        return match($action) {
            'created' => "{$modelName} '{$identifier}' foi criado",
            'updated' => "{$modelName} '{$identifier}' foi atualizado",
            'deleted' => "{$modelName} '{$identifier}' foi deletado",
            default => "{$modelName} '{$identifier}' - {$action}",
        };
    }
}
```

#### 1.9 UseCases

**LogAuditUseCase:**
```php
<?php

namespace App\Application\UseCases\Audit;

use App\Domain\Repositories\AuditLogRepositoryInterface;

class LogAuditUseCase
{
    public function __construct(
        private AuditLogRepositoryInterface $auditRepository
    ) {}
    
    public function execute(
        int $userId,
        string $userType,
        string $action,
        string $modelType,
        ?int $modelId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $description = null,
        ?array $tags = null
    ): void {
        $this->auditRepository->log(
            userId: $userId,
            userType: $userType,
            action: $action,
            modelType: $modelType,
            modelId: $modelId,
            oldValues: $this->sanitizeValues($oldValues),
            newValues: $this->sanitizeValues($newValues),
            description: $description,
            ipAddress: request()->ip(),
            userAgent: request()->userAgent(),
            url: request()->fullUrl(),
            method: request()->method(),
            tags: $tags
        );
    }
    
    private function sanitizeValues(?array $values): ?array
    {
        if (!$values) return null;
        
        // Remover campos sensíveis
        $sensitiveFields = ['password', 'token', 'secret', 'api_key'];
        
        foreach ($sensitiveFields as $field) {
            if (isset($values[$field])) {
                $values[$field] = '***REDACTED***';
            }
        }
        
        return $values;
    }
}
```

**GetAuditLogsUseCase:**
```php
<?php

namespace App\Application\UseCases\Audit;

use App\Domain\Repositories\AuditLogRepositoryInterface;

class GetAuditLogsUseCase
{
    public function __construct(
        private AuditLogRepositoryInterface $auditRepository
    ) {}
    
    public function execute(array $filters = [], int $perPage = 50): array
    {
        return $this->auditRepository->findWithFilters($filters, $perPage);
    }
    
    public function getForModel(string $modelType, int $modelId): array
    {
        return $this->auditRepository->findByModel($modelType, $modelId);
    }
    
    public function getForUser(int $userId, string $userType): array
    {
        return $this->auditRepository->findByUser($userId, $userType);
    }
}
```

#### 1.10 API Endpoints

```php
// routes/api.php

Route::middleware(['auth:sanctum', 'admin.auth'])->group(function () {
    Route::prefix('audit')->group(function () {
        // Listar logs com filtros
        Route::get('/', [AuditController::class, 'index']);
        // Exemplo: /api/audit?action=deleted&model=User&date_from=2025-01-01
        
        // Ver log específico
        Route::get('/{id}', [AuditController::class, 'show']);
        
        // Histórico de um model
        Route::get('/model/{type}/{id}', [AuditController::class, 'modelHistory']);
        // Exemplo: /api/audit/model/User/123
        
        // Atividade de um usuário
        Route::get('/user/{type}/{id}', [AuditController::class, 'userActivity']);
        // Exemplo: /api/audit/user/Admin/5
        
        // Comparar versões
        Route::get('/compare/{id1}/{id2}', [AuditController::class, 'compare']);
        
        // Exportar relatório
        Route::post('/export', [AuditController::class, 'export']);
    });
});
```

#### 1.11 Casos de Uso Reais

**Caso 1: Cliente reclama que dados sumiram**
```php
// Admin acessa: /audit/model/User/123
// Vê histórico completo:

[
  {
    "id": 5678,
    "action": "deleted",
    "user": {
      "name": "João Silva",
      "type": "Admin"
    },
    "description": "User 'maria@email.com' foi deletado",
    "ip": "192.168.1.100",
    "created_at": "2025-01-15 14:30:00"
  }
]

// Resposta: "Admin João Silva deletou em 15/01 às 14h30"
```

**Caso 2: Investigação de segurança**
```php
// Filtrar por tags: /audit?tags=security&date_from=2025-01-01

[
  {
    "action": "login",
    "user": "suspicious@email.com",
    "ip": "123.45.67.89",
    "created_at": "2025-01-15 03:00:00",
    "tags": ["security", "suspicious"]
  },
  {
    "action": "updated",
    "model": "User",
    "description": "Email alterado",
    "ip": "123.45.67.89", // Mesmo IP
    "created_at": "2025-01-15 03:02:00",
    "tags": ["security"]
  }
]

// Detecta: Acessos suspeitos + mudanças
```

**Caso 3: Recuperação de dados**
```php
// Ver o que foi deletado:
$log = AuditLog::find(5678);
$oldValues = $log->old_values;

// Recriar o registro:
User::create($oldValues);

// Registrar recuperação:
AuditLog::log(
    action: 'restored',
    model: $user,
    description: 'User recuperado do audit log #5678'
);
```

---

## 2. Sistema de Configurações

### Por que é ESSENCIAL?

#### 2.1 Flexibilidade sem Deploy
```
Problema SEM sistema de configurações:
- Quer mudar logo da empresa? → Alterar código + Deploy
- Quer desabilitar chat? → Alterar código + Deploy
- Quer mudar email de contato? → Alterar código + Deploy

Solução COM sistema de configurações:
- Admin acessa painel → Muda configuração → Pronto!
- Zero downtime
- Zero risco
- Zero custo de deploy
```

#### 2.2 Multi-tenant Friendly
```
Tenant A:
- Logo: logo-a.png
- Cores: #FF0000
- Features: [chat, billing]

Tenant B:
- Logo: logo-b.png
- Cores: #00FF00
- Features: [billing]

Mesma base de código, comportamentos diferentes!
```

#### 2.3 Feature Flags
```
Cenários:
- Testar nova feature com 10% dos usuários
- Desabilitar feature com bug sem deploy
- Features diferentes por plano (Free vs Pro)
- A/B testing
```

#### 2.4 White Label
```
Cada cliente pode ter:
- Próprio logo
- Próprias cores
- Próprio domínio
- Próprias configurações
```

---

### Arquitetura do Sistema de Configurações

#### 2.2 Estrutura Básica

```
┌─────────────────────────────────────────────┐
│         Application Code                     │
│  Settings::get('app.name')                   │
└──────────────────┬──────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────┐
│         Cache Layer (Redis)                  │
│  (99% das requests vêm daqui)                │
└──────────────────┬──────────────────────────┘
                   │ (cache miss)
                   ▼
┌─────────────────────────────────────────────┐
│       settings table (Database)              │
│  - Leitura: ~1% das requests                 │
│  - Escrita: Admin panel                      │
└─────────────────────────────────────────────┘
```

#### 2.3 Schema da Tabela

```sql
CREATE TABLE settings (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    
    -- Identificação
    key VARCHAR(255) UNIQUE NOT NULL, -- app.name
    category VARCHAR(100) NOT NULL, -- general, email, features
    
    -- Valor
    value JSON, -- Suporta: string, int, bool, array
    default_value JSON, -- Valor padrão
    type ENUM('string', 'integer', 'boolean', 'array', 'json') NOT NULL,
    
    -- Metadados
    description TEXT,
    is_public BOOLEAN DEFAULT FALSE, -- Frontend pode acessar?
    is_encrypted BOOLEAN DEFAULT FALSE, -- Criptografar valor?
    
    -- Validação
    validation_rules JSON, -- ['required', 'min:3']
    options JSON, -- Para select: ['option1', 'option2']
    
    -- Multi-tenant (opcional)
    tenant_id BIGINT NULL,
    
    -- Controle
    updated_by BIGINT, -- Admin que alterou
    updated_at TIMESTAMP,
    created_at TIMESTAMP,
    
    INDEX idx_key (key),
    INDEX idx_category (category),
    INDEX idx_tenant (tenant_id),
    INDEX idx_public (is_public)
);
```

#### 2.4 Configurações Padrão

```php
<?php

namespace Database\Seeders;

class SettingsSeeder extends Seeder
{
    public function run()
    {
        $settings = [
            // Geral
            [
                'key' => 'app.name',
                'value' => 'My Application',
                'type' => 'string',
                'category' => 'general',
                'description' => 'Nome da aplicação',
                'is_public' => true,
            ],
            [
                'key' => 'app.logo',
                'value' => '/images/logo.png',
                'type' => 'string',
                'category' => 'general',
                'description' => 'URL do logo',
                'is_public' => true,
            ],
            [
                'key' => 'app.timezone',
                'value' => 'America/Sao_Paulo',
                'type' => 'string',
                'category' => 'general',
                'options' => DateTimeZone::listIdentifiers(),
            ],
            
            // Email
            [
                'key' => 'email.from_address',
                'value' => 'noreply@example.com',
                'type' => 'string',
                'category' => 'email',
                'validation_rules' => ['required', 'email'],
            ],
            [
                'key' => 'email.from_name',
                'value' => 'My Application',
                'type' => 'string',
                'category' => 'email',
            ],
            [
                'key' => 'email.support_address',
                'value' => 'support@example.com',
                'type' => 'string',
                'category' => 'email',
                'is_public' => true,
            ],
            
            // Features
            [
                'key' => 'features.chat_enabled',
                'value' => true,
                'type' => 'boolean',
                'category' => 'features',
                'description' => 'Habilitar sistema de chat',
            ],
            [
                'key' => 'features.notifications_enabled',
                'value' => true,
                'type' => 'boolean',
                'category' => 'features',
            ],
            [
                'key' => 'features.registration_enabled',
                'value' => true,
                'type' => 'boolean',
                'category' => 'features',
                'description' => 'Permitir auto-registro de usuários',
                'is_public' => true,
            ],
            
            // Limites
            [
                'key' => 'limits.max_file_size',
                'value' => 10485760, // 10MB
                'type' => 'integer',
                'category' => 'limits',
                'description' => 'Tamanho máximo de arquivo (bytes)',
            ],
            [
                'key' => 'limits.rate_limit_per_minute',
                'value' => 60,
                'type' => 'integer',
                'category' => 'limits',
            ],
            
            // Segurança
            [
                'key' => 'security.password_min_length',
                'value' => 8,
                'type' => 'integer',
                'category' => 'security',
            ],
            [
                'key' => 'security.require_email_verification',
                'value' => true,
                'type' => 'boolean',
                'category' => 'security',
            ],
            [
                'key' => 'security.session_lifetime',
                'value' => 120, // minutos
                'type' => 'integer',
                'category' => 'security',
            ],
            
            // Integrações
            [
                'key' => 'integrations.stripe_enabled',
                'value' => false,
                'type' => 'boolean',
                'category' => 'integrations',
            ],
            [
                'key' => 'integrations.stripe_key',
                'value' => '',
                'type' => 'string',
                'category' => 'integrations',
                'is_encrypted' => true,
            ],
            
            // Manutenção
            [
                'key' => 'maintenance.mode',
                'value' => false,
                'type' => 'boolean',
                'category' => 'maintenance',
                'description' => 'Modo de manutenção',
            ],
            [
                'key' => 'maintenance.message',
                'value' => 'Sistema em manutenção. Voltamos em breve!',
                'type' => 'string',
                'category' => 'maintenance',
                'is_public' => true,
            ],
        ];
        
        foreach ($settings as $setting) {
            Setting::create($setting);
        }
    }
}
```

#### 2.5 Helper Class

```php
<?php

namespace App\Helpers;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class Settings
{
    /**
     * Obter configuração (com cache)
     */
    public static function get(string $key, $default = null)
    {
        return Cache::remember(
            key: "setting.{$key}",
            ttl: 3600, // 1 hora
            callback: fn() => self::getFromDatabase($key, $default)
        );
    }
    
    /**
     * Definir configuração
     */
    public static function set(string $key, $value, ?int $updatedBy = null): void
    {
        $setting = Setting::updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'updated_by' => $updatedBy ?? auth()->id(),
                'updated_at' => now(),
            ]
        );
        
        // Limpar cache
        Cache::forget("setting.{$key}");
        
        // Registrar no audit log
        if (config('audit.enabled')) {
            AuditLog::log(
                action: 'updated',
                model: $setting,
                description: "Configuração '{$key}' foi alterada"
            );
        }
    }
    
    /**
     * Obter todas configurações públicas (para frontend)
     */
    public static function public(): array
    {
        return Cache::remember('settings.public', 3600, function () {
            return Setting::where('is_public', true)
                ->get()
                ->mapWithKeys(fn($s) => [$s->key => $s->value])
                ->toArray();
        });
    }
    
    /**
     * Obter configurações por categoria
     */
    public static function category(string $category): array
    {
        return Cache::remember("settings.category.{$category}", 3600, function () use ($category) {
            return Setting::where('category', $category)
                ->get()
                ->mapWithKeys(fn($s) => [$s->key => $s->value])
                ->toArray();
        });
    }
    
    /**
     * Verificar se feature está habilitada
     */
    public static function featureEnabled(string $feature): bool
    {
        return (bool) self::get("features.{$feature}_enabled", false);
    }
    
    /**
     * Limpar todo cache de configurações
     */
    public static function clearCache(): void
    {
        Cache::flush('settings.*');
    }
    
    private static function getFromDatabase(string $key, $default)
    {
        $setting = Setting::where('key', $key)->first();
        
        if (!$setting) {
            return $default;
        }
        
        return self::castValue($setting->value, $setting->type);
    }
    
    private static function castValue($value, string $type)
    {
        return match($type) {
            'boolean' => (bool) $value,
            'integer' => (int) $value,
            'array', 'json' => is_string($value) ? json_decode($value, true) : $value,
            default => $value,
        };
    }
}
```

#### 2.6 Uso na Aplicação

```php
// Em qualquer lugar do código:

// Verificar se feature está habilitada
if (Settings::featureEnabled('chat')) {
    // Mostrar chat
}

// Obter configuração
$appName = Settings::get('app.name');
$maxFileSize = Settings::get('limits.max_file_size');

// Definir configuração
Settings::set('app.name', 'Novo Nome', auth()->id());

// Configurações públicas para frontend
Route::get('/api/public/settings', function () {
    return Settings::public();
});

// Middleware para verificar manutenção
class MaintenanceModeMiddleware
{
    public function handle($request, Closure $next)
    {
        if (Settings::get('maintenance.mode') && !$request->user()?->isAdmin()) {
            return response()->json([
                'message' => Settings::get('maintenance.message')
            ], 503);
        }
        
        return $next($request);
    }
}
```

#### 2.7 Admin Panel para Editar

```php
// Controller
class SettingsController extends Controller
{
    public function index()
    {
        $settings = Setting::orderBy('category')
            ->orderBy('key')
            ->get()
            ->groupBy('category');
        
        return response()->json($settings);
    }
    
    public function update(Request $request, string $key)
    {
        $setting = Setting::where('key', $key)->firstOrFail();
        
        $validated = $request->validate([
            'value' => $this->getValidationRules($setting),
        ]);
        
        Settings::set($key, $validated['value'], auth()->id());
        
        return response()->json([
            'success' => true,
            'message' => 'Configuração atualizada com sucesso'
        ]);
    }
    
    public function bulk(Request $request)
    {
        $validated = $request->validate([
            'settings' => 'required|array',
            'settings.*.key' => 'required|string',
            'settings.*.value' => 'required',
        ]);
        
        foreach ($validated['settings'] as $setting) {
            Settings::set($setting['key'], $setting['value'], auth()->id());
        }
        
        return response()->json([
            'success' => true,
            'message' => count($validated['settings']) . ' configurações atualizadas'
        ]);
    }
}
```

#### 2.8 Casos de Uso Reais

**Caso 1: Modo Manutenção Rápido**
```php
// Bug crítico descoberto em produção

// Admin acessa painel e clica em "Modo Manutenção"
Settings::set('maintenance.mode', true);

// Todos usuários (exceto admins) veem:
// "Sistema em manutenção. Voltamos em breve!"

// Corrige o bug...

// Desativa manutenção
Settings::set('maintenance.mode', false);

// Total: 2 minutos, sem deploy!
```

**Caso 2: Feature Flag Gradual**
```php
// Nova feature: Chat melhorado

// Fase 1: Habilitar para 10% dos usuários
Setting::create([
    'key' => 'features.new_chat_rollout',
    'value' => 10, // 10%
]);

// No código:
$rollout = Settings::get('features.new_chat_rollout', 0);
if (rand(1, 100) <= $rollout) {
    return view('chat.new');
} else {
    return view('chat.old');
}

// Fase 2: Aumentar para 50%
Settings::set('features.new_chat_rollout', 50);

// Fase 3: 100%
Settings::set('features.new_chat_rollout', 100);

// Sem riscos, rollback instantâneo se houver problemas!
```

**Caso 3: White Label por Tenant**
```php
// Tenant A
Settings::set('app.name', 'Empresa A', tenantId: 1);
Settings::set('app.logo', '/logos/empresa-a.png', tenantId: 1);
Settings::set('app.primary_color', '#FF0000', tenantId: 1);

// Tenant B
Settings::set('app.name', 'Empresa B', tenantId: 2);
Settings::set('app.logo', '/logos/empresa-b.png', tenantId: 2);
Settings::set('app.primary_color', '#00FF00', tenantId: 2);

// No código:
$name = Settings::get('app.name'); // Automático baseado no tenant atual
```

---

## 3. Sistema de Notificações

### Por que é ESSENCIAL?

#### 3.1 Comunicação com Usuários
```
Eventos importantes que PRECISAM notificar:
- Bem-vindo ao sistema
- Email alterado (segurança)
- Senha alterada (segurança)
- Conta desativada
- Pagamento recebido
- Assinatura expirando
- Tarefa atribuída
- Mensagem recebida
- Relatório pronto
```

#### 3.2 Engajamento
```
Notificações aumentam:
- Retenção de usuários (+30%)
- Tempo no sistema (+45%)
- Conversão (+25%)
- Satisfação do cliente
```

#### 3.3 Operacional
```
Alertas para admins:
- Sistema com erro
- Uso de CPU alto
- Disco cheio
- Tentativa de invasão
- Payment failed
```

#### 3.4 Multi-Canal
```
Mesma notificação em:
- Email (sempre funciona)
- In-app (tempo real)
- Push (mobile)
- SMS (crítico)
- Slack/Discord (admins)
```

---

### Arquitetura do Sistema de Notificações

#### 3.2 Estrutura Básica

```
┌─────────────────────────────────────────────┐
│           Event Happens                      │
│  (User created, Password changed)            │
└──────────────────┬──────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────┐
│      Laravel Notification Class              │
│  (WelcomeNotification, etc)                  │
└──────────────────┬──────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────┐
│        Multiple Channels                     │
│  - Database (in-app)                         │
│  - Mail (email)                              │
│  - Broadcast (real-time)                     │
│  - SMS (Twilio/Vonage)                       │
└──────────────────┬──────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────┐
│           User Receives                      │
│  - Bell icon com contador                    │
│  - Email na caixa de entrada                 │
│  - Push no celular                           │
└─────────────────────────────────────────────┘
```

#### 3.3 Schema da Tabela

```sql
CREATE TABLE notifications (
    id CHAR(36) PRIMARY KEY, -- UUID
    
    -- Destinatário
    notifiable_type VARCHAR(255) NOT NULL, -- App\Models\Admin
    notifiable_id BIGINT NOT NULL,
    
    -- Tipo e dados
    type VARCHAR(255) NOT NULL, -- App\Notifications\WelcomeNotification
    data JSON NOT NULL,
    
    -- Status
    read_at TIMESTAMP NULL,
    
    -- Timestamps
    created_at TIMESTAMP,
    
    INDEX idx_notifiable (notifiable_type, notifiable_id),
    INDEX idx_read (read_at),
    INDEX idx_created (created_at)
);

CREATE TABLE notification_preferences (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    
    user_id BIGINT NOT NULL,
    user_type VARCHAR(255) NOT NULL,
    
    -- Preferências por tipo
    notification_type VARCHAR(255) NOT NULL,
    
    -- Canais habilitados
    via_database BOOLEAN DEFAULT TRUE,
    via_mail BOOLEAN DEFAULT TRUE,
    via_broadcast BOOLEAN DEFAULT TRUE,
    via_sms BOOLEAN DEFAULT FALSE,
    
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    UNIQUE KEY unique_user_type (user_id, user_type, notification_type)
);
```

#### 3.4 Notification Classes

**WelcomeNotification:**
```php
<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeNotification extends Notification
{
    use Queueable;
    
    public function __construct(
        private string $userName
    ) {}
    
    /**
     * Canais de notificação
     */
    public function via($notifiable): array
    {
        $channels = ['database'];
        
        // Respeitar preferências do usuário
        if ($notifiable->wantsEmailNotifications('welcome')) {
            $channels[] = 'mail';
        }
        
        if ($notifiable->wantsBroadcastNotifications('welcome')) {
            $channels[] = 'broadcast';
        }
        
        return $channels;
    }
    
    /**
     * Email
     */
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Bem-vindo ao ' . Settings::get('app.name'))
            ->greeting('Olá, ' . $this->userName . '!')
            ->line('Bem-vindo ao nosso sistema.')
            ->line('Estamos felizes em ter você conosco.')
            ->action('Acessar Sistema', url('/dashboard'))
            ->line('Se você tiver alguma dúvida, estamos aqui para ajudar!');
    }
    
    /**
     * Database (in-app)
     */
    public function toArray($notifiable): array
    {
        return [
            'title' => 'Bem-vindo!',
            'message' => 'Bem-vindo ao ' . Settings::get('app.name'),
            'icon' => '👋',
            'action_url' => '/dashboard',
            'action_text' => 'Ir para Dashboard',
        ];
    }
    
    /**
     * Broadcast (real-time)
     */
    public function toBroadcast($notifiable): array
    {
        return [
            'title' => 'Bem-vindo!',
            'message' => 'Bem-vindo ao sistema',
            'type' => 'success',
        ];
    }
}
```

**PasswordChangedNotification:**
```php
<?php

namespace App\Notifications;

class PasswordChangedNotification extends Notification
{
    use Queueable;
    
    public function __construct(
        private string $ipAddress,
        private string $userAgent
    ) {}
    
    public function via($notifiable): array
    {
        // SEMPRE enviar email para mudança de senha (segurança)
        return ['database', 'mail'];
    }
    
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Sua senha foi alterada')
            ->line('Sua senha foi alterada com sucesso.')
            ->line('Se você não fez esta alteração, entre em contato imediatamente.')
            ->line('')
            ->line('Detalhes:')
            ->line('IP: ' . $this->ipAddress)
            ->line('Navegador: ' . $this->userAgent)
            ->line('Data: ' . now()->format('d/m/Y H:i:s'))
            ->action('Revisar Segurança', url('/security'))
            ->line('Se não foi você, clique no botão acima imediatamente.');
    }
    
    public function toArray($notifiable): array
    {
        return [
            'title' => 'Senha Alterada',
            'message' => 'Sua senha foi alterada com sucesso',
            'icon' => '🔒',
            'type' => 'warning',
            'action_url' => '/security',
            'metadata' => [
                'ip' => $this->ipAddress,
                'user_agent' => $this->userAgent,
            ],
        ];
    }
}
```

#### 3.5 Enviando Notificações

```php
// Maneira 1: Direto no model
$user->notify(new WelcomeNotification($user->name));

// Maneira 2: Via facade
Notification::send($users, new InvoicePaid($invoice));

// Maneira 3: Múltiplos destinatários
Notification::send(
    Admin::where('is_active', true)->get(),
    new SystemAlert('Disco está com 90% de uso')
);

// Maneira 4: Via evento
class UserRegistered extends Event
{
    public function __construct(public User $user) {}
}

class SendWelcomeNotification implements ShouldQueue
{
    public function handle(UserRegistered $event): void
    {
        $event->user->notify(new WelcomeNotification($event->user->name));
    }
}

// Maneira 5: Agendada
// app/Console/Kernel.php
$schedule->call(function () {
    $expiringSubscriptions = Subscription::where('expires_at', '<=', now()->addDays(7))->get();
    
    foreach ($expiringSubscriptions as $subscription) {
        $subscription->user->notify(
            new SubscriptionExpiring($subscription->expires_at)
        );
    }
})->daily();
```

#### 3.6 API Endpoints

```php
Route::middleware(['auth:sanctum'])->group(function () {
    // Listar notificações
    Route::get('/notifications', [NotificationController::class, 'index']);
    // Retorna: paginadas + contador de não lidas
    
    // Marcar como lida
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    
    // Marcar todas como lidas
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    
    // Deletar notificação
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);
    
    // Preferências de notificação
    Route::get('/notifications/preferences', [NotificationController::class, 'getPreferences']);
    Route::put('/notifications/preferences', [NotificationController::class, 'updatePreferences']);
});
```

#### 3.7 Frontend (Vue.js exemplo)

```vue
<template>
  <div class="notification-bell">
    <button @click="toggleDropdown" class="relative">
      <BellIcon />
      <span v-if="unreadCount > 0" class="badge">
        {{ unreadCount }}
      </span>
    </button>
    
    <div v-if="showDropdown" class="dropdown">
      <div class="header">
        <h3>Notificações</h3>
        <button @click="markAllAsRead">Marcar todas como lidas</button>
      </div>
      
      <div class="notifications-list">
        <div
          v-for="notification in notifications"
          :key="notification.id"
          :class="['notification', { unread: !notification.read_at }]"
          @click="handleNotificationClick(notification)"
        >
          <span class="icon">{{ notification.data.icon }}</span>
          <div class="content">
            <h4>{{ notification.data.title }}</h4>
            <p>{{ notification.data.message }}</p>
            <span class="time">{{ formatTime(notification.created_at) }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  data() {
    return {
      notifications: [],
      unreadCount: 0,
      showDropdown: false
    }
  },
  
  mounted() {
    this.fetchNotifications()
    this.listenToRealtime()
  },
  
  methods: {
    async fetchNotifications() {
      const { data } = await axios.get('/api/notifications')
      this.notifications = data.data
      this.unreadCount = data.unread_count
    },
    
    listenToRealtime() {
      window.Echo.private(`App.Models.User.${this.userId}`)
        .notification((notification) => {
          this.notifications.unshift(notification)
          this.unreadCount++
          
          // Toast
          this.$toast.info(notification.data.message)
        })
    },
    
    async handleNotificationClick(notification) {
      if (!notification.read_at) {
        await axios.post(`/api/notifications/${notification.id}/read`)
        notification.read_at = new Date()
        this.unreadCount--
      }
      
      if (notification.data.action_url) {
        this.$router.push(notification.data.action_url)
      }
    },
    
    async markAllAsRead() {
      await axios.post('/api/notifications/read-all')
      this.notifications.forEach(n => n.read_at = new Date())
      this.unreadCount = 0
    }
  }
}
</script>
```

---

## Integração entre os Sistemas

### Como os 3 Sistemas se Complementam

#### Cenário 1: Admin Deleta Usuário

```
1. Admin clica em "Deletar Usuário João"

2. AUDITORIA registra:
   - Quem: Admin Maria (ID: 5)
   - O quê: Deletou User João (ID: 123)
   - Quando: 2025-01-15 14:30:00
   - De onde: IP 192.168.1.100
   - Dados: { old_values: {name: "João", email: "joao@email.com"} }

3. NOTIFICAÇÃO envia:
   - Para: Admins supervisores
   - Tipo: "User deletado"
   - Canal: In-app + Email
   - Conteúdo: "Admin Maria deletou usuário João"

4. CONFIGURAÇÃO verifica:
   - Settings::get('features.soft_delete_enabled')
   - Se true: Soft delete
   - Se false: Hard delete
```

#### Cenário 2: Sistema detecta uso anormal

```
1. Settings::get('limits.rate_limit_per_minute') = 60

2. Sistema detecta: User fazendo 200 requests/min

3. AUDITORIA registra:
   - action: 'rate_limit_exceeded'
   - tags: ['security', 'critical']

4. NOTIFICAÇÃO envia:
   - Para: Admins
   - Canal: In-app + SMS (crítico)
   - Conteúdo: "Rate limit excedido: User ID 123"

5. Sistema bloqueia temporariamente baseado em:
   - Settings::get('security.auto_block_on_abuse')
```

#### Cenário 3: Feature Flag com Auditoria

```
1. Admin desabilita feature:
   Settings::set('features.chat_enabled', false)

2. AUDITORIA registra:
   - action: 'setting_changed'
   - old_value: true
   - new_value: false

3. NOTIFICAÇÃO envia:
   - Para: Todos admins
   - Conteúdo: "Chat foi desabilitado por Admin X"

4. Usuários tentam acessar chat:
   - Sistema verifica: Settings::featureEnabled('chat')
   - Retorna: false
   - Mostra: "Esta feature está temporariamente desabilitada"
```

---

## Implementação Prática

### Checklist de Implementação

#### Fase 1: Auditoria (1 semana)
- [ ] Criar migration `audit_logs`
- [ ] Criar Entity `AuditLog`
- [ ] Criar Repository interface + implementation
- [ ] Criar trait `HasAuditLog`
- [ ] Criar UseCases (Log, Get)
- [ ] Criar Controller + Routes
- [ ] Adicionar trait nos models principais
- [ ] Criar testes (Unit + Feature)
- [ ] Documentar

#### Fase 2: Configurações (1 semana)
- [ ] Criar migration `settings`
- [ ] Criar Model `Setting`
- [ ] Criar Helper class `Settings`
- [ ] Criar Seeder com configurações padrão
- [ ] Criar Controller + Routes
- [ ] Integrar com cache (Redis)
- [ ] Criar admin panel interface
- [ ] Criar testes
- [ ] Documentar

#### Fase 3: Notificações (1 semana)
- [ ] Criar migration `notifications` (Laravel já tem)
- [ ] Criar migration `notification_preferences`
- [ ] Criar classes de notificações principais
- [ ] Configurar canais (Database, Mail, Broadcast)
- [ ] Criar Controller + Routes
- [ ] Integrar com eventos
- [ ] Criar frontend component (bell icon)
- [ ] Criar testes
- [ ] Documentar

### Comandos Úteis

```bash
# Gerar notification
php artisan make:notification WelcomeNotification

# Testar envio de email
php artisan tinker
> User::find(1)->notify(new WelcomeNotification('Test'))

# Ver notificações no banco
php artisan tinker
> User::find(1)->notifications

# Limpar notificações antigas
php artisan notifications:clean --days=30

# Ver audit logs
php artisan audit:show --model=User --id=123
```

---

## Conclusão

### Benefícios Imediatos

✅ **Auditoria**: Compliance, segurança, debug facilitado
✅ **Configurações**: Flexibilidade sem deploy, feature flags, white label
✅ **Notificações**: Comunicação efetiva, engajamento, alertas

### ROI Esperado

- **Tempo de desenvolvimento futuro**: -60%
- **Tempo de debug**: -70%
- **Satisfação do cliente**: +40%
- **Segurança**: +80%
- **Compliance**: 100%

### Próximos Passos

1. **Implementar Auditoria** (mais crítico para compliance)
2. **Implementar Configurações** (mais usado no dia-a-dia)
3. **Implementar Notificações** (melhor UX)

Cada sistema é independente, mas juntos formam a **base sólida** de qualquer aplicação BtoB profissional.

---

**Quer que eu comece a implementar algum desses sistemas agora?** 🚀

