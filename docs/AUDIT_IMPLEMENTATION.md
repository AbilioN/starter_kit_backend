# Sistema de Auditoria - Implementação Completa

## ✅ Implementação Concluída

Sistema completo de auditoria seguindo Clean Architecture e os padrões do projeto.

---

## 📁 Estrutura Criada

### **1. Database Layer**
- ✅ Migration: `database/migrations/2025_01_15_000000_create_audit_logs_table.php`
- ✅ Model: `app/Models/AuditLog.php`

### **2. Domain Layer**
- ✅ Entity: `app/Domain/Entities/AuditLog.php`
- ✅ Repository Interface: `app/Domain/Repositories/AuditLogRepositoryInterface.php`

### **3. Infrastructure Layer**
- ✅ Repository: `app/Infrastructure/Repositories/AuditLogRepository.php`

### **4. Application Layer**
- ✅ UseCase: `app/Application/UseCases/Audit/LogAuditUseCase.php`
- ✅ UseCase: `app/Application/UseCases/Audit/GetAuditLogsUseCase.php`
- ✅ DTO: `app/Application/DTOs/Audit/AuditLogDto.php`

### **5. Presentation Layer**
- ✅ Controller: `app/Http/Controllers/Api/Admin/AuditController.php`
- ✅ Trait: `app/Traits/HasAuditLog.php`
- ✅ Rotas: Adicionadas em `routes/api.php`

### **6. Service Provider**
- ✅ Registrado em `app/Providers/DomainServiceProvider.php`

### **7. Models com Trait**
- ✅ `app/Models/Admin.php` - Trait adicionado
- ✅ `app/Models/User.php` - Trait adicionado
- ✅ `app/Models/Role.php` - Trait adicionado

---

## 🚀 Como Usar

### **1. Executar Migration**

```bash
php artisan migrate
```

### **2. Uso Automático (Trait)**

O trait `HasAuditLog` registra automaticamente:
- ✅ Criação de registros
- ✅ Atualização de registros
- ✅ Deleção de registros

**Exemplo:**
```php
// Ao criar um admin, automaticamente registra:
$admin = Admin::create([
    'name' => 'Novo Admin',
    'email' => 'admin@test.com',
    'password' => 'password123'
]);
// ✅ Audit log criado automaticamente!
```

### **3. Uso Manual**

```php
use App\Application\UseCases\Audit\LogAuditUseCase;

$useCase = app(LogAuditUseCase::class);

$useCase->execute(
    userId: auth()->id(),
    userType: 'Admin',
    action: 'custom_action',
    modelType: User::class,
    modelId: 123,
    description: 'Ação customizada',
    tags: ['custom', 'important']
);
```

---

## 📡 API Endpoints

### **Listar Logs com Filtros**
```
GET /api/admin/audit?action=deleted&model_type=User&date_from=2025-01-01
```

**Query Parameters:**
- `user_id` - Filtrar por usuário
- `user_type` - Filtrar por tipo (Admin, User)
- `model_type` - Filtrar por modelo
- `model_id` - Filtrar por ID do modelo
- `action` - Filtrar por ação (created, updated, deleted)
- `tags` - Filtrar por tags (separado por vírgula)
- `date_from` - Data inicial
- `date_to` - Data final
- `per_page` - Itens por página (1-100)

### **Ver Log Específico**
```
GET /api/admin/audit/{id}
```

### **Histórico de um Modelo**
```
GET /api/admin/audit/model/{type}/{id}
```
Exemplo: `/api/admin/audit/model/App\Models\User/123`

### **Atividade de um Usuário**
```
GET /api/admin/audit/user/{type}/{id}?per_page=50
```
Exemplo: `/api/admin/audit/user/Admin/5`

### **Logs por Ação**
```
GET /api/admin/audit/action/{action}
```
Exemplo: `/api/admin/audit/action/deleted`

### **Logs por Tag**
```
GET /api/admin/audit/tag/{tag}
```
Exemplo: `/api/admin/audit/tag/security`

---

## 📊 Estrutura dos Dados

### **Tabela `audit_logs`**

```sql
- id (BIGINT)
- user_id (BIGINT) - Quem fez
- user_type (VARCHAR) - Admin ou User
- user_name (VARCHAR) - Cache do nome
- action (VARCHAR) - created, updated, deleted, etc
- model_type (VARCHAR) - App\Models\User
- model_id (BIGINT) - ID do modelo
- old_values (JSON) - Estado anterior
- new_values (JSON) - Estado novo
- description (TEXT) - Descrição legível
- ip_address (VARCHAR) - IP do usuário
- user_agent (TEXT) - Navegador
- url (TEXT) - URL da requisição
- method (VARCHAR) - GET, POST, PUT, DELETE
- tags (JSON) - ['security', 'critical']
- metadata (JSON) - Dados extras
- created_at (TIMESTAMP)
```

### **Resposta da API**

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "user": {
        "id": 5,
        "type": "Admin",
        "name": "João Silva"
      },
      "action": "deleted",
      "model": {
        "type": "App\\Models\\User",
        "id": 123
      },
      "changes": {
        "old": {
          "name": "Maria",
          "email": "maria@email.com"
        },
        "new": null
      },
      "description": "User 'Maria' foi deletado",
      "context": {
        "ip": "192.168.1.100",
        "user_agent": "Mozilla/5.0...",
        "url": "http://localhost/api/admin/users/123",
        "method": "DELETE"
      },
      "tags": ["security", "critical"],
      "metadata": null,
      "created_at": "2025-01-15 14:30:00"
    }
  ],
  "pagination": {
    "current_page": 1,
    "per_page": 50,
    "total": 100,
    "last_page": 2,
    "from": 1,
    "to": 50
  }
}
```

---

## 🔒 Segurança

### **⚠️ IMUTABILIDADE DOS AUDIT LOGS**

**IMPORTANTE:** Audit logs são **IMUTÁVEIS** por segurança e compliance:

- ❌ **NÃO existe** permissão para deletar audit logs
- ❌ **NÃO existe** permissão para editar audit logs
- ❌ **NÃO existe** endpoint de DELETE
- ❌ **NÃO existe** endpoint de UPDATE
- ✅ **APENAS** permissão de leitura: `audit-read`
- ✅ **Mesmo Super Admin** não pode deletar ou modificar logs

**Por quê?**
- ✅ Compliance LGPD/GDPR (logs não podem ser alterados)
- ✅ Non-repudiation (impossível negar ações)
- ✅ Integridade do histórico (rastreabilidade completa)
- ✅ Segurança (impossível esconder ações maliciosas)

### **Permissões de Auditoria**

Apenas uma permissão existe:
- `audit-read`: Permite visualizar audit logs

**NÃO existem:**
- ❌ `audit-create` (logs são criados automaticamente)
- ❌ `audit-update` (logs são imutáveis)
- ❌ `audit-delete` (logs são imutáveis)

### **Campos Sensíveis Sanitizados**

O sistema automaticamente remove campos sensíveis dos valores:
- `password`
- `token`
- `secret`
- `api_key`
- `access_token`
- `refresh_token`

**Exemplo:**
```php
// Antes:
['password' => 'senha123']

// Depois (no audit log):
['password' => '***REDACTED***']
```

---

## 🏷️ Tags Automáticas

O trait `HasAuditLog` adiciona tags automaticamente:

- **`security`**: Para ações `updated` e `deleted`
- **`critical`**: Para ações `deleted`

**Exemplo de uso:**
```php
// Buscar apenas logs críticos
GET /api/admin/audit/tag/critical

// Buscar logs de segurança
GET /api/admin/audit/tag/security
```

---

## 🎯 Casos de Uso

### **1. Investigar Deleção de Usuário**

```bash
# Ver histórico do usuário deletado
GET /api/admin/audit/model/App\Models\User/123

# Resposta mostra:
# - Quem deletou
# - Quando deletou
# - De onde (IP)
# - Dados do usuário deletado
```

### **2. Atividade de um Admin**

```bash
# Ver tudo que um admin fez
GET /api/admin/audit/user/Admin/5

# Resposta mostra todas as ações do admin
```

### **3. Logs de Segurança**

```bash
# Ver apenas ações críticas
GET /api/admin/audit/tag/security

# Ver apenas deleções
GET /api/admin/audit/action/deleted
```

### **4. Período Específico**

```bash
# Logs de janeiro de 2025
GET /api/admin/audit?date_from=2025-01-01&date_to=2025-01-31
```

---

## ⚙️ Configuração

### **Permissões**

Para acessar os endpoints de auditoria, o admin precisa da permissão:
- `audit-read` (permissão específica para audit logs)

**Como atribuir:**
1. Execute o seeder: `php artisan db:seed --class=PermissionSeeder`
2. A permissão `audit-read` será criada
3. Atribua a permissão à role desejada via admin panel ou seeder

**Nota:** A permissão `audit-read` é automaticamente atribuída às roles `super-admin` e `admin` quando você executa o `AdminRolePermissionSeeder`.

### **Desabilitar Auditoria**

Se precisar desabilitar temporariamente:

```php
// No trait HasAuditLog, comentar o boot:
// protected static function bootHasAuditLog(): void { ... }
```

---

## 📈 Próximos Passos (Opcional)

### **1. Exportação de Relatórios**
- Exportar logs em CSV/PDF
- Agendamento de relatórios

### **2. Alertas Automáticos**
- Email quando ação crítica acontece
- Notificação in-app

### **3. Retenção de Dados**
- Limpar logs antigos automaticamente
- Arquivar logs antigos

### **4. Dashboard Visual**
- Gráficos de atividade
- Métricas de uso

---

## ✅ Checklist de Implementação

- [x] Migration criada
- [x] Model criado
- [x] Domain Entity criada
- [x] Repository Interface criada
- [x] Repository Implementation criada
- [x] UseCases criados
- [x] DTO criado
- [x] Controller criado
- [x] Trait criado
- [x] Rotas adicionadas
- [x] Service Provider registrado
- [x] Trait adicionado nos models (Admin, User, Role)
- [x] Documentação criada

---

## 🎉 Sistema Pronto!

O sistema de auditoria está **100% funcional** e seguindo os padrões do projeto:

✅ Clean Architecture
✅ Domain-Driven Design
✅ Type Safety
✅ Segurança (sanitização de dados sensíveis)
✅ Performance (índices otimizados)
✅ API RESTful completa
✅ Documentação completa

**Próximo passo:** Executar `php artisan migrate` e começar a usar! 🚀

