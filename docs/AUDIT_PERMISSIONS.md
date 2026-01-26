# Permissões de Auditoria - Segurança e Compliance

## 🔒 Princípio de Imutabilidade

**Audit logs são IMUTÁVEIS por design** - isso é fundamental para segurança e compliance.

### ❌ O que NÃO existe (e nunca existirá):

- ❌ Permissão `audit-delete`
- ❌ Permissão `audit-update` 
- ❌ Permissão `audit-edit`
- ❌ Endpoint `DELETE /api/admin/audit/{id}`
- ❌ Endpoint `PUT /api/admin/audit/{id}`
- ❌ Endpoint `PATCH /api/admin/audit/{id}`

### ✅ O que existe:

- ✅ Permissão `audit-read` - Apenas visualização
- ✅ Endpoints GET apenas (leitura)

---

## 📋 Permissão Criada

### **`audit-read`**

```php
Permission::create([
    'slug' => 'audit-read',
    'name' => 'View Audit Logs',
    'description' => 'Allows viewing audit logs and system activity history',
    'resource' => 'audit',
    'action' => 'read',
    'route' => 'audit/read',
]);
```

**Características:**
- ✅ Permite visualizar todos os audit logs
- ✅ Permite usar todos os filtros
- ✅ Permite ver histórico de modelos
- ✅ Permite ver atividade de usuários
- ❌ **NÃO permite** deletar logs
- ❌ **NÃO permite** editar logs
- ❌ **NÃO permite** criar logs manualmente (são criados automaticamente)

---

## 🛡️ Por que Imutabilidade?

### **1. Compliance Legal**

**LGPD (Brasil):**
- Art. 48: Empresas devem manter registro de acesso a dados pessoais
- Logs não podem ser alterados para manter integridade

**GDPR (Europa):**
- Requer rastreabilidade completa
- Logs alterados invalidam compliance

**SOC 2 Type II:**
- Requer auditoria imutável
- Logs alterados = falha na auditoria

### **2. Segurança**

- **Non-repudiation**: Impossível negar ações realizadas
- **Integridade**: Histórico completo e confiável
- **Detecção de fraudes**: Impossível esconder ações maliciosas
- **Investigação**: Logs são evidência confiável

### **3. Boas Práticas**

- **Princípio do menor privilégio**: Apenas leitura necessária
- **Separation of concerns**: Logs são apenas para consulta
- **Audit trail integrity**: Rastreabilidade completa

---

## 🔐 Controle de Acesso

### **Quem pode ver audit logs?**

Apenas admins com a permissão `audit-read`:

```php
// No controller
$this->authorizeActionUseCase->execute($admin, 'audit-read');
```

### **Super Admin pode deletar?**

**NÃO!** Mesmo Super Admin não pode deletar ou editar audit logs.

```php
// Não existe endpoint de delete
// Não existe permissão de delete
// Não existe código que permita delete
```

### **Como atribuir permissão?**

1. **Via Seeder (Automático):**
   ```bash
   php artisan db:seed --class=PermissionSeeder
   php artisan db:seed --class=AdminRolePermissionSeeder
   ```
   A permissão `audit-read` será automaticamente atribuída às roles `super-admin` e `admin`.

2. **Via Admin Panel:**
   - Acesse gerenciamento de roles
   - Adicione a permissão `audit-read` à role desejada

3. **Via API:**
   ```bash
   POST /api/admin/role/update-permissions
   {
     "role_id": 1,
     "permission_ids": [1, 2, 3, ..., "audit-read_id"]
   }
   ```

---

## 📊 Endpoints Disponíveis

Todos os endpoints são **GET apenas** (leitura):

| Endpoint | Método | Permissão | Descrição |
|----------|--------|-----------|-----------|
| `/api/admin/audit` | GET | `audit-read` | Lista logs com filtros |
| `/api/admin/audit/{id}` | GET | `audit-read` | Ver log específico |
| `/api/admin/audit/model/{type}/{id}` | GET | `audit-read` | Histórico de modelo |
| `/api/admin/audit/user/{type}/{id}` | GET | `audit-read` | Atividade de usuário |
| `/api/admin/audit/action/{action}` | GET | `audit-read` | Logs por ação |
| `/api/admin/audit/tag/{tag}` | GET | `audit-read` | Logs por tag |

**Nenhum endpoint de DELETE ou UPDATE existe!**

---

## 🚨 Importante

### **Se precisar limpar logs antigos:**

**Opção 1: Via Database (Cuidado!)**
```sql
-- Apenas em casos extremos e com backup
DELETE FROM audit_logs WHERE created_at < '2024-01-01';
```

**Opção 2: Criar comando Artisan (Recomendado)**
```bash
php artisan audit:clean --days=365
```

**⚠️ ATENÇÃO:** 
- Sempre faça backup antes
- Documente a ação no sistema
- Considere compliance antes de deletar

### **Se precisar corrigir um log:**

**NÃO É POSSÍVEL!** 

Se um log foi criado incorretamente:
1. Crie um novo log explicando o erro
2. Use tags para marcar como `correction`
3. Mantenha o log original (histórico completo)

---

## ✅ Checklist de Segurança

- [x] Apenas permissão de leitura existe
- [x] Nenhum endpoint de delete/update
- [x] Controller documentado com avisos de imutabilidade
- [x] Super Admin não pode deletar
- [x] Campos sensíveis são sanitizados
- [x] Logs são criados automaticamente (não manualmente)
- [x] Documentação completa sobre imutabilidade

---

## 🎯 Conclusão

O sistema de auditoria foi projetado com **segurança máxima**:

✅ **Imutável**: Logs não podem ser alterados ou deletados
✅ **Compliance**: Atende LGPD, GDPR, SOC 2
✅ **Segurança**: Non-repudiation garantido
✅ **Rastreabilidade**: Histórico completo e confiável

**Mesmo Super Admin não pode quebrar essas regras!** 🛡️

