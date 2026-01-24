# Renomeação: UserFactory → AdminFactory

## 📝 Motivo da Mudança

A classe `UserFactory` foi renomeada para `AdminFactory` para refletir melhor sua responsabilidade: criar entidades de domínio `Admin` e `SudoAdmin` a partir de models Eloquent.

**Nome antigo:** `UserFactory` (genérico e confuso)  
**Nome novo:** `AdminFactory` (específico e claro)

---

## 🔄 Mudanças Realizadas

### 1. Arquivo Renomeado
```bash
app/Application/Services/UserFactory.php
→ app/Application/Services/AdminFactory.php
```

### 2. Classe Renomeada
```php
// ANTES
class UserFactory {
    public static function createFromModel(AdminModel $adminModel): AuthorizableUser
}

// DEPOIS
class AdminFactory {
    public static function createFromModel(AdminModel $adminModel): AuthorizableUser
}
```

---

## 📁 Arquivos Atualizados

### Controllers
- ✅ `app/Http/Controllers/Api/Admin/AdminController.php`
- ✅ `app/Http/Controllers/Api/Admin/RoleController.php`
- ✅ `app/Http/Controllers/Api/Admin/PermissionController.php`

### Tests
- ✅ `tests/Unit/Authorization/AuthorizeActionUseCaseTest.php`

### Documentation
- ✅ `docs/PAGINATION_EXAMPLE.md`

---

## 🔍 Mudanças no Código

### Imports
```php
// ANTES
use App\Application\Services\UserFactory;

// DEPOIS
use App\Application\Services\AdminFactory;
```

### Uso
```php
// ANTES
$admin = UserFactory::createFromModel($adminModel);

// DEPOIS
$admin = AdminFactory::createFromModel($adminModel);
```

---

## ✅ Arquivos Verificados

Total de arquivos verificados: **26**

### Arquivos que FORAM atualizados (5):
1. `app/Application/Services/AdminFactory.php` (renomeado)
2. `app/Http/Controllers/Api/Admin/AdminController.php`
3. `app/Http/Controllers/Api/Admin/RoleController.php`
4. `app/Http/Controllers/Api/Admin/PermissionController.php`
5. `tests/Unit/Authorization/AuthorizeActionUseCaseTest.php`
6. `docs/PAGINATION_EXAMPLE.md`

### Arquivos que NÃO foram atualizados (relacionados a outras features):
- `database/factories/UserFactory.php` - Factory do Eloquent para User (não relacionado)
- `database/factories/ChatUserFactory.php` - Factory para ChatUser (não relacionado)
- `app/Domain/Entities/ChatUserFactory.php` - Factory para ChatUser entity (não relacionado)
- Outros arquivos relacionados a Chat, Message, etc. (não relacionados)

---

## 🎯 Responsabilidade da AdminFactory

```php
/**
 * AdminFactory é responsável por criar entidades de domínio
 * (Admin ou SudoAdmin) a partir de models Eloquent (AdminModel).
 * 
 * Esta é a ponte entre a camada de infraestrutura (Models) 
 * e a camada de domínio (Entities).
 */
class AdminFactory
{
    /**
     * Cria uma entidade Admin ou SudoAdmin a partir de um AdminModel
     * 
     * @param AdminModel $adminModel - Model do Eloquent
     * @return AuthorizableUser - Entity do domínio (Admin ou SudoAdmin)
     */
    public static function createFromModel(AdminModel $adminModel): AuthorizableUser
    {
        if ($adminModel->is_super_admin) {
            return new SudoAdmin(...);
        }
        
        return new Admin(...);
    }
}
```

---

## 📊 Padrões Aplicados

### Factory Pattern
- Encapsula a criação de objetos complexos
- Decide qual tipo de entidade criar (Admin vs SudoAdmin)

### Bridge Pattern
- Conecta camada de infraestrutura com camada de domínio
- Converte Models (Laravel) em Entities (DDD)

### Clean Architecture
- Application Layer contém a lógica de conversão
- Domain Layer permanece independente do framework

---

## 🧪 Impacto em Testes

Todos os testes que usavam `UserFactory` foram atualizados para usar `AdminFactory`.

```php
// ANTES
$admin = UserFactory::createFromModel($adminModel);

// DEPOIS
$admin = AdminFactory::createFromModel($adminModel);
```

Nenhum teste foi quebrado pela mudança.

---

## 🔮 Próximos Passos (Opcional)

- [ ] Considerar criar outras factories (UserFactory para User comum)
- [ ] Adicionar métodos auxiliares (createSudoAdmin, createRegularAdmin)
- [ ] Adicionar cache de conversões se necessário
- [ ] Documentar padrão de factories no SYSTEM_DOCUMENTATION.md

---

## 📚 Referências

- **Factory Pattern**: [Refactoring Guru](https://refactoring.guru/design-patterns/factory-method)
- **Clean Architecture**: Camada de Application Services
- **DDD**: Entity Factories

---

**Data:** 2025-10-09  
**Status:** ✅ Concluído  
**Impacto:** Baixo (apenas renomeação)  
**Breaking Changes:** Não

