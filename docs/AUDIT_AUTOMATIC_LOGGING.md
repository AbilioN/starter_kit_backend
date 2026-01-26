# Audit Log Automático - Como Funciona

## ✅ Sim! Funciona Automaticamente

**Qualquer ação que você tomar será registrada automaticamente nos audit logs**, desde que:

1. ✅ O model tenha o trait `HasAuditLog`
2. ✅ A ação use o model Eloquent diretamente (não bulk operations)

---

## 🎯 O que é Registrado Automaticamente

### **Models com Trait `HasAuditLog`:**

- ✅ `Admin` - Todas as ações
- ✅ `User` - Todas as ações  
- ✅ `Role` - Todas as ações

### **Ações Registradas Automaticamente:**

1. **CREATE** - Quando você cria um registro
   ```php
   Admin::create([...]); // ✅ Audit log criado automaticamente
   ```

2. **UPDATE** - Quando você atualiza um registro
   ```php
   $admin->update([...]); // ✅ Audit log criado automaticamente
   ```

3. **DELETE** - Quando você deleta um registro
   ```php
   $admin->delete(); // ✅ Audit log criado automaticamente
   ```

---

## 📋 Exemplos Práticos

### **Exemplo 1: Deletar Usuário via Sudo Admin**

```php
// UseCase: DeleteAdminUseCase
public function execute(int $id): void
{
    $admin = $this->adminRepository->findById($id);
    $this->adminRepository->delete($id);
}

// Repository: AdminRepository
public function delete(int $id): void
{
    AdminModel::findOrFail($id)->delete(); // ✅ Dispara evento 'deleted'
}

// Model: Admin (tem trait HasAuditLog)
// Evento 'deleted' é capturado → Audit log criado automaticamente!
```

**Resultado:**
```json
{
  "action": "deleted",
  "user": {
    "id": 1,
    "type": "Admin",
    "name": "Sudo Admin"
  },
  "model": {
    "type": "App\\Models\\Admin",
    "id": 5
  },
  "description": "Admin 'João Silva' foi deletado",
  "tags": ["security", "critical"]
}
```

### **Exemplo 2: Criar Admin**

```php
// UseCase: CreateAdminUseCase
$admin = $this->adminRepository->create($name, $email, $password);

// Repository: AdminRepository
$admin = AdminModel::create([...]); // ✅ Dispara evento 'created'

// Audit log criado automaticamente!
```

### **Exemplo 3: Atualizar Role**

```php
// UseCase: UpdateRoleUseCase
$role = $this->roleRepository->update($id, $slug, $name, $description);

// Repository: RoleRepository
$role = RoleModel::findOrFail($id);
$role->update([...]); // ✅ Dispara evento 'updated'

// Audit log criado automaticamente!
```

---

## ⚠️ Importante: Bulk Operations NÃO Funcionam

### **❌ NÃO Dispara Audit Log:**

```php
// Bulk delete - NÃO dispara eventos
AdminModel::where('id', $id)->delete(); // ❌ SEM audit log

// Bulk update - NÃO dispara eventos  
AdminModel::where('id', $id)->update([...]); // ❌ SEM audit log
```

### **✅ Dispara Audit Log:**

```php
// Delete individual - Dispara eventos
AdminModel::findOrFail($id)->delete(); // ✅ COM audit log

// Update individual - Dispara eventos
$admin = AdminModel::findOrFail($id);
$admin->update([...]); // ✅ COM audit log
```

---

## 🔧 Correções Aplicadas

Todos os repositories foram corrigidos para usar o model diretamente:

### **Antes (❌ Não funcionava):**
```php
// RoleRepository
public function delete(int $id): void
{
    RoleModel::where('id', $id)->delete(); // ❌ Bulk delete
}
```

### **Depois (✅ Funciona):**
```php
// RoleRepository
public function delete(int $id): void
{
    RoleModel::findOrFail($id)->delete(); // ✅ Delete individual
}
```

**Repositories Corrigidos:**
- ✅ `AdminRepository::delete()`
- ✅ `RoleRepository::delete()`
- ✅ `RoleRepository::update()`
- ✅ `PermissionRepository::delete()`

---

## 🎯 Fluxo Completo

```
┌─────────────────────────────────────────────────────────┐
│              AÇÃO DO USUÁRIO                             │
│  (Sudo Admin deleta usuário via API)                    │
└──────────────────┬──────────────────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────────────────┐
│              CONTROLLER                                  │
│  DeleteAdminController::delete()                        │
└──────────────────┬──────────────────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────────────────┐
│              USE CASE                                    │
│  DeleteAdminUseCase::execute()                          │
└──────────────────┬──────────────────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────────────────┐
│              REPOSITORY                                  │
│  AdminRepository::delete()                              │
│  AdminModel::findOrFail($id)->delete()                  │
└──────────────────┬──────────────────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────────────────┐
│              ELOQUENT EVENT                             │
│  Evento 'deleted' disparado                             │
└──────────────────┬──────────────────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────────────────┐
│              TRAIT HasAuditLog                           │
│  static::deleted(function ($model) {                    │
│      $model->logAudit('deleted', ...);                  │
│  })                                                      │
└──────────────────┬──────────────────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────────────────┐
│              USE CASE LogAuditUseCase                    │
│  Registra no banco de dados                             │
└──────────────────┬──────────────────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────────────────┐
│              AUDIT LOG CRIADO                            │
│  ✅ Registro imutável no banco                          │
└─────────────────────────────────────────────────────────┘
```

---

## 📊 O que é Capturado Automaticamente

### **Dados do Usuário:**
- ✅ ID do usuário autenticado
- ✅ Tipo (Admin/User)
- ✅ Nome (cache)

### **Dados da Ação:**
- ✅ Ação (created, updated, deleted)
- ✅ Model type (App\Models\Admin)
- ✅ Model ID

### **Dados de Mudança:**
- ✅ Valores antigos (old_values)
- ✅ Valores novos (new_values)
- ✅ Campos sensíveis sanitizados automaticamente

### **Contexto:**
- ✅ IP address
- ✅ User agent
- ✅ URL da requisição
- ✅ Método HTTP (GET, POST, PUT, DELETE)

### **Tags Automáticas:**
- ✅ `security` - Para ações updated/deleted
- ✅ `critical` - Para ações deleted

---

## ✅ Checklist de Funcionamento

Para garantir que audit log funcione:

- [x] Model tem trait `HasAuditLog`
- [x] Repository usa model diretamente (não bulk operations)
- [x] Usuário está autenticado (`auth()->user()`)
- [x] Ação é create, update ou delete

---

## 🧪 Como Testar

### **1. Deletar um Admin:**

```bash
# Via API
DELETE /api/admin/admins
{
  "id": 5
}

# Verificar audit log
GET /api/admin/audit?action=deleted&model_type=App\Models\Admin
```

### **2. Criar um Admin:**

```bash
# Via API
POST /api/admin/admins
{
  "name": "Novo Admin",
  "email": "novo@test.com",
  "password": "password123"
}

# Verificar audit log
GET /api/admin/audit?action=created&model_type=App\Models\Admin
```

### **3. Atualizar uma Role:**

```bash
# Via API
PUT /api/admin/role/update
{
  "id": 1,
  "name": "Nova Role",
  "description": "Nova descrição"
}

# Verificar audit log
GET /api/admin/audit?action=updated&model_type=App\Models\Role
```

---

## 🎯 Resposta à Pergunta

**"Se eu deletar um usuário pelo sudo admin, vai estar no audit logs?"**

**✅ SIM!** 

Funciona automaticamente em **qualquer UseCase** que:
1. Use um repository que chama o model diretamente
2. O model tenha o trait `HasAuditLog`
3. O usuário esteja autenticado

**Exemplo:**
- ✅ `DeleteAdminUseCase` → `AdminRepository::delete()` → `AdminModel::delete()` → **Audit log criado!**
- ✅ `DeleteRoleUseCase` → `RoleRepository::delete()` → `RoleModel::delete()` → **Audit log criado!**
- ✅ `CreateAdminUseCase` → `AdminRepository::create()` → `AdminModel::create()` → **Audit log criado!**

**Tudo funciona automaticamente!** 🎉

