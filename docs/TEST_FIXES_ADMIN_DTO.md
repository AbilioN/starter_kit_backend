# Correções de Testes - Admin DTO

## 🐛 Problema Identificado

O teste `AdminLoginUseCaseTest::test_execute_returns_admin_data_and_token_on_successful_login` estava falhando porque:

1. A entidade `Admin` agora tem campos `createdAt` e `updatedAt`
2. O `AdminDto` inclui esses campos no `toArray()`
3. Os testes não estavam passando esses campos ao criar instâncias de `Admin`
4. Os testes não esperavam esses campos no resultado

## ❌ Erro

```
Failed asserting that two arrays are equal.
--- Expected
+++ Actual
@@ @@
     'is_active' => true
     'is_super_admin' => false
     'last_login_at' => null
+    'created_at' => null
+    'updated_at' => null
 )
 'token' => 'test_token_123'
 'roles' => []
)
```

## ✅ Correção Aplicada

### Arquivo Corrigido
- `tests/Unit/Application/Auth/AdminLoginUseCaseTest.php`

### Mudanças nos Testes

#### 1. `test_execute_returns_admin_data_and_token_on_successful_login`

**Antes:**
```php
$admin = new Admin(
    id: 1,
    name: 'Test Admin',
    email: 'admin@test.com',
    password: 'hashed_password',
    isActive: true,
    lastLoginAt: null
);

// Assert
$this->assertEquals([
    'admin' => [
        'id' => 1,
        'name' => 'Test Admin',
        'email' => 'admin@test.com',
        'is_active' => true,
        'is_super_admin' => false,
        'last_login_at' => null
    ],
    'token' => 'test_token_123',
    'roles' => []
], $result);
```

**Depois:**
```php
$admin = new Admin(
    id: 1,
    name: 'Test Admin',
    email: 'admin@test.com',
    password: 'hashed_password',
    isActive: true,
    isSuperAdmin: false,  // ✅ Adicionado
    lastLoginAt: null,
    createdAt: null,      // ✅ Adicionado
    updatedAt: null       // ✅ Adicionado
);

// Assert
$this->assertEquals([
    'admin' => [
        'id' => 1,
        'name' => 'Test Admin',
        'email' => 'admin@test.com',
        'is_active' => true,
        'is_super_admin' => false,
        'last_login_at' => null,
        'created_at' => null,    // ✅ Adicionado
        'updated_at' => null     // ✅ Adicionado
    ],
    'token' => 'test_token_123',
    'roles' => []
], $result);
```

#### 2. `test_execute_formats_last_login_at_when_present`

**Antes:**
```php
$lastLoginAt = new \DateTime('2025-06-27 20:45:30');
$admin = new Admin(
    id: 1,
    name: 'Test Admin',
    email: 'admin@test.com',
    password: 'hashed_password',
    isActive: true,
    lastLoginAt: $lastLoginAt
);

// Assert
$this->assertEquals('2025-06-27 20:45:30', $result['admin']['last_login_at']);
```

**Depois:**
```php
$lastLoginAt = new \DateTime('2025-06-27 20:45:30');
$createdAt = new \DateTime('2025-06-01 10:00:00');    // ✅ Adicionado
$updatedAt = new \DateTime('2025-06-27 20:45:30');    // ✅ Adicionado

$admin = new Admin(
    id: 1,
    name: 'Test Admin',
    email: 'admin@test.com',
    password: 'hashed_password',
    isActive: true,
    isSuperAdmin: false,   // ✅ Adicionado
    lastLoginAt: $lastLoginAt,
    createdAt: $createdAt,  // ✅ Adicionado
    updatedAt: $updatedAt   // ✅ Adicionado
);

// Assert
$this->assertEquals('2025-06-27 20:45:30', $result['admin']['last_login_at']);
$this->assertEquals('2025-06-01 10:00:00', $result['admin']['created_at']);    // ✅ Adicionado
$this->assertEquals('2025-06-27 20:45:30', $result['admin']['updated_at']);    // ✅ Adicionado
```

## 📋 Outros Testes Verificados

### Não Necessitam Correção

#### `tests/Unit/Domain/Entities/AdminTest.php`
- ✅ Testes apenas da entidade, não do DTO
- ✅ Não testam conversão para array
- ✅ Campos opcionais podem ser omitidos

#### `tests/Unit/Application/Auth/AdminRegisterUseCaseTest.php`
- ✅ UseCase não usa DTO (monta array manualmente)
- ✅ Testes já incluem verificação de `created_at`
- ✅ Não usa `toDto()->toArray()`

## 🎯 Padrão para Novos Testes

Quando criar testes que usam `Admin` entity e testam o DTO:

```php
// ✅ CORRETO - Incluir todos os campos
$admin = new Admin(
    id: 1,
    name: 'Test Admin',
    email: 'admin@test.com',
    password: 'hashed_password',
    isActive: true,
    isSuperAdmin: false,
    lastLoginAt: null,
    createdAt: null,
    updatedAt: null
);

// Ao testar o DTO
$dto = $admin->toDto()->toArray();
$this->assertArrayHasKey('created_at', $dto);
$this->assertArrayHasKey('updated_at', $dto);
```

## 📊 Estrutura do AdminDto

```php
class AdminDto
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $email,
        public readonly bool $is_active,
        public readonly bool $is_super_admin,
        public readonly ?string $last_login_at = null,
        public readonly ?string $created_at = null,    // ✅ Sempre presente
        public readonly ?string $updated_at = null      // ✅ Sempre presente
    ) {}
}
```

## 🧪 Como Executar os Testes

```bash
# Teste específico corrigido
php artisan test --filter=AdminLoginUseCaseTest

# Todos os testes de Admin
php artisan test --filter=AdminTest

# Todos os testes unitários
php artisan test tests/Unit
```

## ✅ Status

- ✅ `AdminLoginUseCaseTest::test_execute_returns_admin_data_and_token_on_successful_login` - Corrigido
- ✅ `AdminLoginUseCaseTest::test_execute_formats_last_login_at_when_present` - Corrigido
- ✅ Outros testes verificados e não necessitam correção

## 📝 Lições Aprendidas

1. **Campos opcionais com valores padrão**: Quando adicionar campos opcionais a entities que têm DTOs, atualizar todos os testes que verificam o output do DTO

2. **Named parameters**: Usar named parameters facilita adicionar novos campos sem quebrar código existente

3. **Testes de DTO**: Sempre verificar se os testes esperam a estrutura completa do DTO

4. **Mocks vs Entities**: Testes unitários devem criar entities completas, mesmo que alguns campos sejam `null`

---

**Data:** 2025-10-09  
**Status:** ✅ Corrigido  
**Arquivo:** `tests/Unit/Application/Auth/AdminLoginUseCaseTest.php`

