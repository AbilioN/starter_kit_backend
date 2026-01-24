# Documentação Completa do Sistema - Addresses Dashboard

## Índice

1. [Visão Geral do Sistema](#visão-geral-do-sistema)
2. [Arquitetura](#arquitetura)
3. [Estrutura do Projeto](#estrutura-do-projeto)
4. [Módulos Implementados](#módulos-implementados)
5. [Sistema de Autenticação e Autorização](#sistema-de-autenticação-e-autorização)
6. [API Endpoints](#api-endpoints)
7. [Banco de Dados](#banco-de-dados)
8. [Testes](#testes)
9. [Boas Práticas Implementadas](#boas-práticas-implementadas)
10. [Como Executar](#como-executar)

---

## Visão Geral do Sistema

O **Addresses Dashboard** é um sistema completo de gerenciamento administrativo desenvolvido em **Laravel 11** seguindo os princípios de **Clean Architecture** e **Domain-Driven Design (DDD)**. O sistema oferece:

- **Gerenciamento de Administradores** (CRUD completo)
- **Sistema de Roles e Permissões** (RBAC - Role-Based Access Control)
- **Gerenciamento de Usuários**
- **Sistema de Chat** em tempo real com Pusher
- **Autenticação JWT** via Laravel Sanctum
- **Dashboard administrativo**
- **Sistema de notificações** via email

### Tecnologias Principais

- **Backend**: Laravel 11 (PHP 8.2+)
- **Banco de Dados**: MySQL 8.0
- **Autenticação**: Laravel Sanctum
- **Broadcasting**: Pusher
- **Containerização**: Docker + Docker Compose
- **Cache**: Redis
- **Queue**: Redis
- **Frontend**: Vue.js (básico)
- **Testes**: PHPUnit (Feature + Unit Tests)

---

## Arquitetura

### Clean Architecture

O sistema segue os princípios da Clean Architecture, organizando o código em camadas bem definidas:

```
┌─────────────────────────────────────────────┐
│         Presentation Layer (HTTP)           │
│  Controllers, Requests, Middleware          │
└─────────────────┬───────────────────────────┘
                  │
┌─────────────────▼───────────────────────────┐
│        Application Layer (Use Cases)        │
│  Business Logic, DTOs, Services             │
└─────────────────┬───────────────────────────┘
                  │
┌─────────────────▼───────────────────────────┐
│           Domain Layer (Entities)           │
│  Entities, Interfaces, Exceptions           │
└─────────────────┬───────────────────────────┘
                  │
┌─────────────────▼───────────────────────────┐
│      Infrastructure Layer (Database)        │
│  Repositories, Models (Eloquent)            │
└─────────────────────────────────────────────┘
```

#### Camadas Detalhadas

1. **Domain Layer** (`app/Domain/`)
   - **Entities**: Representam os conceitos de negócio (`Admin`, `Role`, `Permission`, `User`, `Chat`, `Message`)
   - **Interfaces**: Contratos para repositórios e serviços
   - **Exceptions**: Exceções customizadas de domínio

2. **Application Layer** (`app/Application/`)
   - **UseCases**: Casos de uso que orquestram a lógica de negócio
   - **DTOs**: Data Transfer Objects para transferir dados entre camadas
   - **Services**: Serviços auxiliares como `UserFactory`

3. **Infrastructure Layer** (`app/Infrastructure/`)
   - **Repositories**: Implementações concretas dos repositórios
   - **Services**: Implementações de serviços externos

4. **Presentation Layer** (`app/Http/`)
   - **Controllers**: Controladores que recebem requisições HTTP
   - **Requests**: Validação de dados de entrada
   - **Middleware**: Filtros de requisição (autenticação, autorização)

---

## Estrutura do Projeto

```
addresses_dashboard/
├── app/
│   ├── Application/
│   │   ├── DTOs/
│   │   │   ├── Admin/
│   │   │   │   └── Authorization/
│   │   │   │       ├── AdminDto.php
│   │   │   │       ├── PermissionDto.php
│   │   │   │       └── RoleDto.php
│   │   │   └── User/
│   │   ├── Services/
│   │   │   └── UserFactory.php
│   │   └── UseCases/
│   │       ├── Admin/
│   │       │   ├── Authorization/
│   │       │   │   ├── AuthorizeActionUseCase.php
│   │       │   │   ├── CheckAdminPermissionUseCase.php
│   │       │   │   ├── GetAllPermissionsUseCase.php
│   │       │   │   └── UpdatePermissionsToRoleUseCase.php
│   │       │   ├── AssignRoleToAdminUseCase.php
│   │       │   ├── CreateAdminUseCase.php
│   │       │   ├── DeleteAdminUseCase.php
│   │       │   ├── GetAllAdminsUseCase.php
│   │       │   └── UpdateAdminUseCase.php
│   │       ├── Auth/
│   │       │   ├── AdminLoginUseCase.php
│   │       │   └── AdminRegisterUseCase.php
│   │       ├── Chat/
│   │       └── User/
│   │
│   ├── Domain/
│   │   ├── Entities/
│   │   │   ├── Admin.php
│   │   │   ├── SudoAdmin.php
│   │   │   ├── Permission.php
│   │   │   ├── Role.php
│   │   │   ├── User.php
│   │   │   ├── Chat.php
│   │   │   └── Message.php
│   │   ├── Exceptions/
│   │   │   ├── AuthenticationException.php
│   │   │   ├── AuthorizationException.php
│   │   │   └── ValidationException.php
│   │   ├── Interfaces/
│   │   │   ├── AuthorizableUser.php
│   │   │   ├── ChatUser.php
│   │   │   └── SudoAdminInterface.php
│   │   ├── Repositories/
│   │   │   ├── AdminRepositoryInterface.php
│   │   │   ├── PermissionRepositoryInterface.php
│   │   │   ├── RoleRepositoryInterface.php
│   │   │   └── UserRepositoryInterface.php
│   │   └── Services/
│   │       ├── AdminAuthServiceInterface.php
│   │       └── AuthServiceInterface.php
│   │
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/
│   │   │   │   ├── Admin/
│   │   │   │   │   ├── AdminController.php
│   │   │   │   │   ├── AdminLoginController.php
│   │   │   │   │   ├── AdminRegisterController.php
│   │   │   │   │   ├── DashboardController.php
│   │   │   │   │   ├── PermissionController.php
│   │   │   │   │   ├── RoleController.php
│   │   │   │   │   └── UserController.php
│   │   │   │   └── Chat/
│   │   │   │       └── ChatController.php
│   │   │   └── Auth/
│   │   ├── Middleware/
│   │   │   └── AdminAuthMiddleware.php
│   │   └── Requests/
│   │       ├── AdminLoginRequest.php
│   │       ├── CreateAdminRequest.php
│   │       └── UpdateAdminRequest.php
│   │
│   ├── Infrastructure/
│   │   ├── Repositories/
│   │   │   ├── AdminRepository.php
│   │   │   ├── PermissionRepository.php
│   │   │   ├── RoleRepository.php
│   │   │   └── EloquentUserRepository.php
│   │   └── Services/
│   │       ├── AdminAuthService.php
│   │       └── AuthService.php
│   │
│   ├── Models/
│   │   ├── Admin.php
│   │   ├── AdminRole.php
│   │   ├── AdminRolePermission.php
│   │   ├── Permission.php
│   │   ├── Role.php
│   │   ├── RolePermission.php
│   │   ├── User.php
│   │   ├── Chat.php
│   │   └── Message.php
│   │
│   └── Providers/
│       ├── AppServiceProvider.php
│       └── DomainServiceProvider.php
│
├── database/
│   ├── migrations/
│   ├── seeders/
│   │   ├── AdminSeeder.php
│   │   ├── RoleSeeder.php
│   │   ├── PermissionSeeder.php
│   │   └── AdminRolePermissionSeeder.php
│   └── factories/
│
├── tests/
│   ├── Feature/
│   │   ├── Admin/
│   │   │   ├── AdminsTest.php (17 testes)
│   │   │   ├── AdminAuthMiddlewareTest.php
│   │   │   ├── PermissionTest.php (4 testes)
│   │   │   └── RoleManagementTest.php (13 testes)
│   │   ├── Auth/
│   │   │   └── AdminLoginTest.php
│   │   └── Chat/
│   └── Unit/
│       ├── Authorization/
│       │   └── AuthorizeActionUseCaseTest.php
│       └── Application/
│           └── Auth/
│               └── AdminLoginUseCaseTest.php (4 testes)
│
└── routes/
    ├── api.php
    ├── web.php
    └── channels.php
```

---

## Módulos Implementados

### 1. Sistema de Autenticação

#### Autenticação de Administradores

**Endpoints:**
- `POST /api/admin/login` - Login de administrador
- `POST /api/admin/register` - Registro de novo administrador (requer permissões)

**Funcionalidades:**
- Login com email e senha
- Geração de token JWT via Laravel Sanctum
- Validação de admin ativo
- Atualização de `last_login_at`
- Retorno de roles e permissões no login

**UseCases:**
- `AdminLoginUseCase`: Autentica admin e retorna token + dados + roles
- `AdminRegisterUseCase`: Registra novo admin no sistema

**Fluxo de Login:**
```
1. Request → AdminLoginController
2. AdminLoginRequest valida dados
3. AdminLoginUseCase executa lógica
4. AdminAuthService autentica
5. Retorna: { admin, token, roles }
```

#### Autenticação de Usuários

Similar ao sistema de admins, mas para usuários regulares.

---

### 2. Sistema de Autorização (RBAC)

#### Conceitos

O sistema implementa um **RBAC (Role-Based Access Control)** robusto com três níveis:

1. **Super Admin (SudoAdmin)**: Tem acesso total, bypass de todas as permissões
2. **Roles**: Grupos de permissões (ex: Admin, Moderator, Editor)
3. **Permissions**: Permissões granulares (ex: admin-create, user-read)

#### Estrutura de Permissões

Cada permissão tem:
- `slug`: Identificador único (ex: `admin-create`)
- `name`: Nome legível
- `description`: Descrição da permissão
- `resource`: Recurso relacionado (admin, user, role, etc)
- `action`: Ação (create, read, update, delete, manage)
- `is_active`: Status da permissão

#### Permissões Implementadas

**Administradores:**
- `admin-create`: Criar administradores
- `admin-read`: Visualizar administradores
- `admin-update`: Editar administradores
- `admin-delete`: Deletar administradores
- `admin-manage`: Gerenciar todos os aspectos de administradores

**Roles:**
- `role-create`: Criar roles
- `role-read`: Visualizar roles
- `role-update`: Editar roles
- `role-delete`: Deletar roles
- `role-manage`: Gerenciar roles e suas permissões

**Usuários:**
- `user-create`: Criar usuários
- `user-read`: Visualizar usuários
- `user-update`: Editar usuários
- `user-delete`: Deletar usuários
- `user-manage`: Gerenciar todos os aspectos de usuários

#### UseCases de Autorização

1. **AuthorizeActionUseCase**
   - Verifica se um admin tem permissão para executar uma ação
   - SudoAdmin tem bypass automático
   - Lança `AuthorizationException` se não autorizado

2. **CheckAdminPermissionUseCase**
   - Verifica permissão específica do admin
   - Busca roles e suas permissões
   - Retorna true/false

3. **UpdatePermissionsToRoleUseCase**
   - Atualiza permissões de uma role (replace/sync)
   - Valida que todas as permissões existem
   - Permite array vazio para remover todas

#### Entities de Autorização

**Admin Entity:**
```php
class Admin implements AuthorizableUser, ChatUser
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $email,
        public readonly bool $isActive,
        public readonly bool $isSuperAdmin = false,
        // ...
    ) {}
    
    public function hasPermission(string $permissionSlug): bool
    {
        // Admin comum não tem permissões diretas
        // Verificadas através de roles no UseCase
        return false;
    }
}
```

**SudoAdmin Entity:**
```php
class SudoAdmin implements SudoAdminInterface, AuthorizableUser
{
    public function hasPermission(string $permissionSlug): bool
    {
        // SudoAdmin tem todas as permissões
        return true;
    }
    
    public function canBypassAllPermissions(): bool
    {
        return true;
    }
}
```

**UserFactory:**
- Converte `Admin` Model (Eloquent) para Domain Entity
- Retorna `SudoAdmin` se `is_super_admin = true`
- Retorna `Admin` caso contrário

---

### 3. Gerenciamento de Roles

#### Endpoints

- `GET /api/admin/roles` - Listar roles (requer `role-read`)
- `POST /api/admin/role/create` - Criar role (requer `role-create`)
- `PUT /api/admin/role/update` - Atualizar role (requer `role-update`)
- `POST /api/admin/role/delete` - Deletar role (requer `role-delete`)
- `POST /api/admin/role/update-permissions` - Atualizar permissões da role (requer `role-manage`)

#### UseCases

1. **CreateRoleUseCase**: Cria nova role
2. **UpdateRoleUseCase**: Atualiza dados da role
3. **DeleteRoleUseCase**: Deleta role
4. **GetAllRolesUseCase**: Lista todas as roles
5. **UpdatePermissionsToRoleUseCase**: Gerencia permissões da role

#### Funcionalidades

- Roles podem ter múltiplas permissões
- Permissões são sincronizadas (replace) via `sync()`
- Cada role tem `slug`, `name`, `description`, `is_active`
- Roles inativas não podem ser atribuídas
- Tabela pivot `role_permissions` com timestamps

#### Exemplo de Role

```json
{
  "id": 1,
  "slug": "admin",
  "name": "Administrator",
  "description": "Full system administrator",
  "is_active": true,
  "permissions": [
    {
      "id": 1,
      "slug": "admin-create",
      "name": "Create Administrator"
    },
    // ... mais permissões
  ]
}
```

---

### 4. Gerenciamento de Administradores

#### Endpoints

- `GET /api/admin/admins` - Listar administradores (requer `admin-read`)
- `POST /api/admin/admins` - Criar administrador (requer `admin-create`)
- `PUT /api/admin/admins` - Atualizar administrador (requer `admin-update`)
- `DELETE /api/admin/admins` - Deletar administrador (requer `admin-delete`)

#### UseCases

1. **GetAllAdminsUseCase**: Lista todos os administradores
2. **CreateAdminUseCase**: Cria novo administrador
3. **UpdateAdminUseCase**: Atualiza dados do administrador
4. **DeleteAdminUseCase**: Deleta administrador
5. **AssignRoleToAdminUseCase**: Atribui role ao administrador

#### Funcionalidades Especiais

**Criação de Admin com Role:**
```json
POST /api/admin/admins
{
  "name": "Novo Admin",
  "email": "admin@example.com",
  "password": "password123",
  "password_confirmation": "password123",
  "is_active": true,
  "role_id": 2  // Opcional: atribui role imediatamente
}
```

**Atribuição de Role:**
- Quando `role_id` é fornecido, `AssignRoleToAdminUseCase` é executado
- Tabela pivot `admin_roles` registra:
  - `admin_id`: ID do admin
  - `role_id`: ID da role
  - `assigned_at`: Timestamp da atribuição
  - `assigned_by`: ID do admin que fez a atribuição

**Validações:**
- Email único
- Senha mínima de 8 caracteres com confirmação
- Role deve existir e estar ativa
- Campos opcionais: `is_active`, `role_id`

---

### 5. Gerenciamento de Permissões

#### Endpoint

- `GET /api/admin/permissions` - Listar todas as permissões (requer `role-manage`)

#### UseCase

**GetAllPermissionsUseCase**: 
- Busca todas as permissões do sistema
- Converte para DTOs
- Retorna array de permissões

#### Estrutura de Resposta

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "slug": "admin-create",
      "name": "Create Administrator",
      "description": "Allows creating new administrators",
      "resource": "admin",
      "action": "create",
      "route": "admin/create",
      "is_active": true,
      "created_at": "2025-01-01 00:00:00",
      "updated_at": "2025-01-01 00:00:00"
    }
  ]
}
```

---

### 6. Sistema de Chat

O sistema inclui um módulo completo de chat em tempo real usando Pusher.

#### Funcionalidades

- Chat privado (1-on-1)
- Chat em grupo
- Suporte para Admin e User como participantes
- Mensagens em tempo real via broadcasting
- Sistema de leitura de mensagens
- Contagem de mensagens não lidas

#### Entidades

**Chat:**
- `id`, `name`, `type` (private/group)
- `created_by`, `created_by_type` (Admin/User)
- `is_active`

**Message:**
- `id`, `chat_id`, `content`
- `sender_id`, `sender_type` (Admin/User)
- `is_read`, `read_at`

**ChatUser Interface:**
- Abstração que permite Admin e User participarem de chats
- Implementada por `Admin` e `User` entities

---

### 7. Middleware

#### AdminAuthMiddleware

Middleware customizado para proteger rotas administrativas:

```php
public function handle(Request $request, Closure $next): Response
{
    $user = $request->user();
    
    // Verifica autenticação
    if (!$user) {
        return response()->json(['message' => 'Unauthenticated.'], 401);
    }

    // Verifica se é Admin
    if (!$user instanceof Admin) {
        return response()->json([
            'message' => 'Access denied. Admin privileges required.'
        ], 403);
    }

    // Verifica se está ativo
    if (!$user->isActive()) {
        return response()->json([
            'message' => 'Access denied. Admin privileges required.'
        ], 403);
    }

    return $next($request);
}
```

**Registro:**
```php
// bootstrap/app.php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'admin.auth' => \App\Http\Middleware\AdminAuthMiddleware::class,
    ]);
})
```

**Uso:**
```php
Route::middleware(['auth:sanctum', 'admin.auth'])->group(function () {
    // Rotas protegidas
});
```

---

## API Endpoints

### Autenticação

| Método | Endpoint | Descrição | Auth | Permissão |
|--------|----------|-----------|------|-----------|
| POST | `/api/admin/login` | Login de admin | Não | - |
| POST | `/api/admin/register` | Registro de admin | Sim | `admin-create` |

### Administradores

| Método | Endpoint | Descrição | Auth | Permissão |
|--------|----------|-----------|------|-----------|
| GET | `/api/admin/admins` | Listar admins | Sim | `admin-read` |
| POST | `/api/admin/admins` | Criar admin | Sim | `admin-create` |
| PUT | `/api/admin/admins` | Atualizar admin | Sim | `admin-update` |
| DELETE | `/api/admin/admins` | Deletar admin | Sim | `admin-delete` |

### Roles

| Método | Endpoint | Descrição | Auth | Permissão |
|--------|----------|-----------|------|-----------|
| GET | `/api/admin/roles` | Listar roles | Sim | `role-read` |
| POST | `/api/admin/role/create` | Criar role | Sim | `role-create` |
| PUT | `/api/admin/role/update` | Atualizar role | Sim | `role-update` |
| POST | `/api/admin/role/delete` | Deletar role | Sim | `role-delete` |
| POST | `/api/admin/role/update-permissions` | Atualizar permissões | Sim | `role-manage` |

### Permissões

| Método | Endpoint | Descrição | Auth | Permissão |
|--------|----------|-----------|------|-----------|
| GET | `/api/admin/permissions` | Listar permissões | Sim | `role-manage` |

### Usuários

| Método | Endpoint | Descrição | Auth | Permissão |
|--------|----------|-----------|------|-----------|
| GET | `/api/admin/users` | Listar usuários | Sim | `user-read` |
| GET | `/api/admin/users/{id}` | Ver usuário | Sim | `user-read` |
| POST | `/api/admin/users` | Criar usuário | Sim | `user-create` |
| PUT | `/api/admin/users/{id}` | Atualizar usuário | Sim | `user-update` |
| DELETE | `/api/admin/users/{id}` | Deletar usuário | Sim | `user-delete` |

### Chat

| Método | Endpoint | Descrição | Auth |
|--------|----------|-----------|------|
| GET | `/api/chats` | Listar chats | Sim |
| POST | `/api/chats/private` | Criar chat privado | Sim |
| POST | `/api/chats/group` | Criar chat em grupo | Sim |
| POST | `/api/chats/{id}/messages` | Enviar mensagem | Sim |
| GET | `/api/chats/{id}/messages` | Listar mensagens | Sim |
| POST | `/api/chats/{id}/mark-read` | Marcar como lido | Sim |

---

## Banco de Dados

### Tabelas Principais

#### admins
```sql
id              BIGINT PRIMARY KEY AUTO_INCREMENT
name            VARCHAR(255) NOT NULL
email           VARCHAR(255) UNIQUE NOT NULL
password        VARCHAR(255) NOT NULL
is_active       BOOLEAN DEFAULT TRUE
is_super_admin  BOOLEAN DEFAULT FALSE
last_login_at   TIMESTAMP NULL
created_at      TIMESTAMP
updated_at      TIMESTAMP
```

#### roles
```sql
id          BIGINT PRIMARY KEY AUTO_INCREMENT
slug        VARCHAR(255) UNIQUE NOT NULL
name        VARCHAR(255) NOT NULL
description TEXT
is_active   BOOLEAN DEFAULT TRUE
created_at  TIMESTAMP
updated_at  TIMESTAMP
```

#### permissions
```sql
id          BIGINT PRIMARY KEY AUTO_INCREMENT
slug        VARCHAR(255) UNIQUE NOT NULL
name        VARCHAR(255) NOT NULL
description TEXT
resource    VARCHAR(255)
action      VARCHAR(255)
route       VARCHAR(255)
is_active   BOOLEAN DEFAULT TRUE
created_at  TIMESTAMP
updated_at  TIMESTAMP
```

#### admin_roles (pivot)
```sql
id          BIGINT PRIMARY KEY AUTO_INCREMENT
admin_id    BIGINT NOT NULL FOREIGN KEY → admins(id)
role_id     BIGINT NOT NULL FOREIGN KEY → roles(id)
assigned_at TIMESTAMP NOT NULL
assigned_by BIGINT NOT NULL FOREIGN KEY → admins(id)
created_at  TIMESTAMP
updated_at  TIMESTAMP

UNIQUE(admin_id, role_id)
```

#### role_permissions (pivot)
```sql
id            BIGINT PRIMARY KEY AUTO_INCREMENT
role_id       BIGINT NOT NULL FOREIGN KEY → roles(id)
permission_id BIGINT NOT NULL FOREIGN KEY → permissions(id)
created_at    TIMESTAMP
updated_at    TIMESTAMP

UNIQUE(role_id, permission_id)
```

#### users
```sql
id         BIGINT PRIMARY KEY AUTO_INCREMENT
name       VARCHAR(255) NOT NULL
email      VARCHAR(255) UNIQUE NOT NULL
password   VARCHAR(255) NOT NULL
created_at TIMESTAMP
updated_at TIMESTAMP
```

#### chats
```sql
id              BIGINT PRIMARY KEY AUTO_INCREMENT
name            VARCHAR(255)
type            ENUM('private', 'group')
created_by      BIGINT NOT NULL
created_by_type VARCHAR(255) NOT NULL
is_active       BOOLEAN DEFAULT TRUE
created_at      TIMESTAMP
updated_at      TIMESTAMP
```

#### messages
```sql
id          BIGINT PRIMARY KEY AUTO_INCREMENT
chat_id     BIGINT NOT NULL FOREIGN KEY → chats(id)
sender_id   BIGINT NOT NULL
sender_type VARCHAR(255) NOT NULL
content     TEXT NOT NULL
is_read     BOOLEAN DEFAULT FALSE
read_at     TIMESTAMP NULL
created_at  TIMESTAMP
updated_at  TIMESTAMP
```

#### chat_user (pivot)
```sql
id        BIGINT PRIMARY KEY AUTO_INCREMENT
chat_id   BIGINT NOT NULL FOREIGN KEY → chats(id)
user_id   BIGINT NOT NULL
user_type VARCHAR(255) NOT NULL
is_active BOOLEAN DEFAULT TRUE
joined_at TIMESTAMP NOT NULL

UNIQUE(chat_id, user_id, user_type)
```

### Relacionamentos

```
Admin ──┬─► AdminRoles ◄─┬── Role ──┬─► RolePermissions ◄─┬── Permission
        │                │          │                       │
        │                │          │                       │
        └────────────────┴──────────┴───────────────────────┘
                  N:M relationship
```

---

## Testes

### Estrutura de Testes

O sistema possui **cobertura completa de testes**, divididos em:

1. **Feature Tests**: Testam a aplicação end-to-end (HTTP → Database)
2. **Unit Tests**: Testam componentes isolados (UseCases, Entities)

### Feature Tests Implementados

#### AdminsTest (17 testes)
```php
✓ super_admin_can_list_all_admins
✓ admin_with_admin_read_can_list_all_admins
✓ admin_without_admin_read_cannot_list_admins
✓ super_admin_can_create_admin
✓ admin_with_admin_create_can_create_admin
✓ admin_without_admin_create_cannot_create_admin
✓ super_admin_can_update_admin
✓ admin_with_admin_update_can_update_admin
✓ admin_without_admin_update_cannot_update_admin
✓ super_admin_can_delete_admin
✓ admin_with_admin_delete_can_delete_admin
✓ admin_without_admin_delete_cannot_delete_admin
✓ unauthenticated_user_cannot_access_admins
✓ cannot_create_admin_with_duplicate_email
✓ cannot_create_admin_without_required_fields
✓ can_create_admin_with_role
✓ cannot_create_admin_with_invalid_role
```

#### RoleManagementTest (13 testes)
```php
✓ an_admin_can_list_roles
✓ an_admin_can_create_a_role
✓ an_admin_can_create_a_role_with_permissions
✓ admin_cannot_create_role_without_create_permission
✓ admin_cannot_list_roles_without_read_permission
✓ an_admin_cannot_update_a_role_without_permission
✓ an_admin_can_update_a_role_when_has_update_permission
✓ an_admin_cannot_update_a_role_when_does_not_have_update_permission
✓ an_admin_can_delete_a_role_when_has_delete_permission
✓ an_admin_cannot_delete_a_role_without_delete_permission
✓ an_admin_cannot_change_permissions_to_a_role_without_manage_permission
✓ an_admin_can_update_permissions_when_has_manage_permission
✓ an_admin_cannot_update_permissions_with_invalid_permission_ids
✓ an_admin_can_remove_all_permissions_from_role
```

#### PermissionTest (4 testes)
```php
✓ super_admin_can_list_all_permissions
✓ admin_with_role_manage_can_list_all_permissions
✓ admin_without_role_manage_cannot_list_permissions
✓ unauthenticated_user_cannot_access_permissions
```

### Unit Tests Implementados

#### AuthorizeActionUseCaseTest (3 testes)
```php
✓ super_admin_can_always_perform_actions
✓ admin_without_permission_throws_authorization_exception
✓ admin_with_permission_can_perform_action
```

#### AdminLoginUseCaseTest (4 testes)
```php
✓ execute_returns_admin_data_and_token_on_successful_login
✓ execute_throws_authentication_exception_when_credentials_are_invalid
✓ execute_throws_authentication_exception_when_admin_is_inactive
✓ execute_formats_last_login_at_when_present
```

### Padrões de Teste

**Feature Test Example:**
```php
public function admin_with_admin_create_can_create_admin(): void
{
    // Arrange: Preparar dados
    $token = $this->adminWithAllPermissions->createToken('test-token')->plainTextToken;
    $adminData = [
        'name' => 'New Admin',
        'email' => 'newadmin@test.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'is_active' => true
    ];
    
    // Act: Executar ação
    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $token
    ])->postJson('/api/admin/admins', $adminData);
    
    // Assert: Verificar resultado
    $response->assertStatus(201);
    $this->assertDatabaseHas('admins', [
        'email' => 'newadmin@test.com'
    ]);
}
```

**Unit Test Example:**
```php
public function test_execute_returns_admin_data_and_token_on_successful_login()
{
    // Arrange: Mock dependencies
    $mockAuthService = Mockery::mock(AdminAuthServiceInterface::class);
    $mockAuthService->shouldReceive('authenticate')->andReturn($admin);
    $mockAuthService->shouldReceive('generateToken')->andReturn('token');
    $mockAuthService->shouldReceive('getAdminRolesWithPermissions')->andReturn([]);
    
    // Act
    $useCase = new AdminLoginUseCase($mockAuthService);
    $result = $useCase->execute('email@test.com', 'password');
    
    // Assert
    $this->assertEquals($expectedResult, $result);
}
```

### Executando Testes

```bash
# Todos os testes
docker-compose exec app php artisan test

# Testes específicos
docker-compose exec app php artisan test --filter=AdminsTest

# Com cobertura
docker-compose exec app php artisan test --coverage

# Parar no primeiro erro
docker-compose exec app php artisan test --stop-on-failure
```

---

## Boas Práticas Implementadas

### 1. Clean Architecture

- **Separação de Camadas**: Domain, Application, Infrastructure, Presentation
- **Dependency Inversion**: Interfaces no Domain, implementações na Infrastructure
- **Use Cases**: Lógica de negócio isolada e testável
- **Entities**: Representam conceitos de negócio puros

### 2. Domain-Driven Design (DDD)

- **Entities**: `Admin`, `Role`, `Permission`, `User`
- **Value Objects**: DTOs imutáveis
- **Repositories**: Abstração de persistência
- **Domain Services**: Lógica que não pertence a uma entidade específica
- **Domain Events**: (preparado para implementação futura)

### 3. SOLID Principles

- **Single Responsibility**: Cada classe tem uma única responsabilidade
- **Open/Closed**: Aberto para extensão, fechado para modificação
- **Liskov Substitution**: `SudoAdmin` e `Admin` implementam `AuthorizableUser`
- **Interface Segregation**: Interfaces específicas (`AuthorizableUser`, `ChatUser`)
- **Dependency Inversion**: Dependências através de interfaces

### 4. Design Patterns

- **Repository Pattern**: Abstração de acesso a dados
- **Factory Pattern**: `UserFactory` para criar entidades corretas
- **Strategy Pattern**: Diferentes estratégias de autorização
- **DTO Pattern**: Transferência de dados entre camadas
- **Service Layer Pattern**: UseCases como serviços de aplicação

### 5. Code Quality

- **Type Hinting**: Tipos em todos os parâmetros e retornos
- **Immutability**: DTOs e Entities com propriedades readonly
- **Validation**: Request classes dedicadas
- **Error Handling**: Exceptions customizadas por domínio
- **Logging**: Sistema de logs para jobs e operações críticas

### 6. Security

- **Authentication**: Laravel Sanctum com tokens
- **Authorization**: RBAC com verificação em múltiplas camadas
- **Password Hashing**: bcrypt para senhas
- **SQL Injection**: Proteção via Eloquent ORM
- **XSS Protection**: Validação de entrada
- **CSRF Protection**: Tokens CSRF para forms

### 7. Testing

- **Test Coverage**: +38 testes implementados
- **TDD**: Testes escritos antes/durante implementação
- **Arrange-Act-Assert**: Padrão AAA em todos os testes
- **Test Isolation**: Cada teste é independente
- **RefreshDatabase**: Banco limpo para cada teste

### 8. API Design

- **RESTful**: Endpoints seguem convenções REST
- **JSON Response**: Formato consistente de resposta
- **HTTP Status Codes**: Uso correto de códigos
- **Validation Errors**: Retorno detalhado de erros
- **API Versioning**: Preparado para versionamento

### 9. Database

- **Migrations**: Controle de versão do schema
- **Seeders**: Dados iniciais e de teste
- **Factories**: Geração de dados falsos
- **Indexes**: Otimização de queries
- **Foreign Keys**: Integridade referencial

### 10. Documentation

- **Code Comments**: Comentários significativos
- **PHPDoc**: Documentação de classes e métodos
- **README**: Documentação de setup
- **API Documentation**: Endpoints documentados
- **Architecture Docs**: Documentação da arquitetura

---

## Como Executar

### Pré-requisitos

- Docker Desktop
- Docker Compose
- Git

### Setup Inicial

1. **Clone o repositório:**
```bash
git clone <repository-url>
cd addresses_dashboard
```

2. **Configure o ambiente:**
```bash
cp .env.example .env
cp env.docker .env.docker
```

3. **Configure variáveis de ambiente no `.env.docker`:**
```env
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=addresses_dashboard
DB_USERNAME=root
DB_PASSWORD=root

PUSHER_APP_ID=your_app_id
PUSHER_APP_KEY=your_app_key
PUSHER_APP_SECRET=your_app_secret
PUSHER_APP_CLUSTER=your_cluster
PUSHER_HOST=api-eu.pusher.com
```

4. **Inicie os containers:**
```bash
docker-compose up -d
```

5. **Instale dependências:**
```bash
docker-compose exec app composer install
docker-compose exec app npm install
```

6. **Gere a chave da aplicação:**
```bash
docker-compose exec app php artisan key:generate
```

7. **Execute as migrations:**
```bash
docker-compose exec app php artisan migrate
```

8. **Execute os seeders:**
```bash
docker-compose exec app php artisan db:seed
```

9. **Gere assets frontend:**
```bash
docker-compose exec app npm run dev
```

### Dados de Acesso Inicial

Após executar os seeders:

**Super Admin:**
- Email: `admin@dashboard.com`
- Password: `password`

**Roles Criadas:**
- `super-admin`: Todas as permissões
- `admin`: Permissões administrativas
- `user`: Permissões básicas

### Executando Testes

```bash
# Todos os testes
docker-compose exec app php artisan test

# Testes específicos
docker-compose exec app php artisan test --filter=AdminsTest
docker-compose exec app php artisan test --testsuite=Feature
docker-compose exec app php artisan test --testsuite=Unit

# Com cobertura
docker-compose exec app php artisan test --coverage
```

### Acessando a Aplicação

- **Frontend**: http://localhost:8000
- **API**: http://localhost:8000/api
- **phpMyAdmin**: http://localhost:8080 (se configurado)

### Comandos Úteis

```bash
# Logs da aplicação
docker-compose exec app tail -f storage/logs/laravel.log

# Limpar cache
docker-compose exec app php artisan cache:clear
docker-compose exec app php artisan config:clear
docker-compose exec app php artisan route:clear

# Listar rotas
docker-compose exec app php artisan route:list

# Criar migration
docker-compose exec app php artisan make:migration create_table_name

# Criar seeder
docker-compose exec app php artisan make:seeder TableSeeder

# Rodar queue worker
docker-compose exec app php artisan queue:work

# Recriar banco (CUIDADO: apaga tudo!)
docker-compose exec app php artisan migrate:fresh --seed
```

### Troubleshooting

**Erro de permissão no storage:**
```bash
docker-compose exec app chmod -R 777 storage bootstrap/cache
```

**Erro de conexão com MySQL:**
```bash
# Verificar se o MySQL está rodando
docker-compose ps

# Verificar logs do MySQL
docker-compose logs mysql
```

**Erro de broadcasting/Pusher:**
```bash
# Verificar configuração no .env.docker
# Testar credenciais do Pusher
```

---

## Estrutura de Resposta da API

### Sucesso

```json
{
  "success": true,
  "data": {
    // ... dados da resposta
  }
}
```

### Erro de Validação (422)

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": [
      "The email has already been taken."
    ],
    "password": [
      "The password must be at least 8 characters."
    ]
  }
}
```

### Erro de Autorização (403)

```json
{
  "error": "Admin 123 does not have permission to perform this action. Required permission: admin-create"
}
```

### Erro de Autenticação (401)

```json
{
  "message": "Unauthenticated."
}
```

### Erro Interno (500)

```json
{
  "error": "Internal server error: Detailed error message"
}
```

---

## Próximos Passos / Roadmap

### Funcionalidades Planejadas

1. **Dashboard Analytics**
   - Estatísticas de usuários
   - Gráficos de atividade
   - Logs de auditoria

2. **Sistema de Notificações**
   - Notificações in-app
   - Email notifications
   - Push notifications

3. **Auditoria Completa**
   - Log de todas as ações
   - Histórico de mudanças
   - Tracking de quem fez o quê

4. **API de Relatórios**
   - Exportação de dados
   - Relatórios customizados
   - Agendamento de relatórios

5. **Melhorias no Chat**
   - Anexos de arquivos
   - Emojis e reações
   - Busca em mensagens
   - Mensagens temporárias

6. **Multi-tenancy**
   - Suporte a múltiplas organizações
   - Isolamento de dados
   - Configurações por tenant

---

## Conclusão

Este sistema foi desenvolvido seguindo as melhores práticas de engenharia de software, com foco em:

- **Manutenibilidade**: Código limpo e bem organizado
- **Testabilidade**: Alta cobertura de testes
- **Escalabilidade**: Arquitetura preparada para crescimento
- **Segurança**: Múltiplas camadas de proteção
- **Performance**: Otimizações e caching
- **Documentação**: Código e sistema bem documentados

A arquitetura Clean Architecture + DDD garante que o sistema seja:
- Fácil de entender
- Fácil de testar
- Fácil de modificar
- Independente de frameworks
- Independente de UI
- Independente de banco de dados

**Total de Linhas de Código**: ~15.000+ linhas
**Total de Testes**: 38+ testes
**Cobertura de Testes**: Alta (funcionalidades críticas 100% cobertas)
**Tempo de Desenvolvimento**: Projeto completo e funcional

---

**Última Atualização**: Janeiro 2025
**Versão**: 1.0.0
**Autor**: Sistema de Addresses Dashboard

