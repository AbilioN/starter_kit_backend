# Guia de Seeding de Dados

## Seeders Disponíveis

### Produção / Base
Os seeders padrão criam apenas dados essenciais:

```bash
php artisan db:seed
```

Isso executa:
- ✅ `RoleSeeder` - Cria roles do sistema
- ✅ `PermissionSeeder` - Cria permissões
- ✅ `SudoAdminSeeder` - Cria apenas o Super Admin
- ✅ Associações de roles e permissões

### Dados de Teste / Desenvolvimento

Para popular o banco com dados de teste:

#### 1. Criar admins adicionais (via Tinker)
```bash
php artisan tinker

# Criar 20 admins
\App\Models\Admin::factory()->count(20)->create();

# Criar 50 admins
\App\Models\Admin::factory()->count(50)->create();

# Criar admins inativos
\App\Models\Admin::factory()->count(10)->create(['is_active' => false]);
```

#### 2. Criar admins adicionais (via Factory em código)
```php
use App\Models\Admin;

// Criar admins aleatórios
Admin::factory()->count(20)->create();

// Criar com atributos específicos
Admin::factory()->create([
    'name' => 'Test Admin',
    'email' => 'test@example.com',
    'is_active' => true,
]);
```

#### 3. Script para popular dados de desenvolvimento

Crie um arquivo `database/seeders/DevelopmentSeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;

class DevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        // Criar 20 admins aleatórios
        Admin::factory()->count(20)->create();
        
        // Criar alguns admins específicos para testes
        Admin::factory()->create([
            'name' => 'Active Admin',
            'email' => 'active@test.com',
            'is_active' => true,
        ]);
        
        Admin::factory()->create([
            'name' => 'Inactive Admin',
            'email' => 'inactive@test.com',
            'is_active' => false,
        ]);
    }
}
```

Depois execute:
```bash
php artisan db:seed --class=DevelopmentSeeder
```

## Estrutura de Seeders

### Seeders de Base (sempre executados)
- `DatabaseSeeder` - Seeder principal
- `RoleSeeder` - Roles do sistema
- `PermissionSeeder` - Permissões
- `AdminSeeder` - Apenas Super Admin
- `AdminRolePermissionSeeder` - Associações

### Seeders Opcionais (para desenvolvimento)
- `DevelopmentSeeder` - Dados de teste

## Resetar e Popular

### Reset completo com seed
```bash
php artisan migrate:fresh --seed
```

### Reset e adicionar dados de desenvolvimento
```bash
php artisan migrate:fresh --seed
php artisan db:seed --class=DevelopmentSeeder
```

## Testes

Os testes usam `RefreshDatabase` e criam seus próprios dados usando factories.

Exemplo:
```php
public function test_example(): void
{
    // Criar 30 admins para teste
    Admin::factory()->count(30)->create();
    
    // Teste...
}
```

## Factory de Admin

A factory está localizada em `database/factories/AdminFactory.php`.

### Uso básico:
```php
// Criar 1 admin
Admin::factory()->create();

// Criar 10 admins
Admin::factory()->count(10)->create();

// Criar com atributos específicos
Admin::factory()->create([
    'email' => 'specific@example.com',
    'is_active' => false,
]);

// Criar inativo
Admin::factory()->inactive()->create();
```

## Credenciais Padrão

### Super Admin (sempre criado)
- Email: `sudo@dashboard.com`
- Password: `password123`
- Super Admin: ✅

### Admins criados via Factory
- Password: `password123` (padrão para todos)
- Email: Gerado aleatoriamente pelo Faker
- Nome: Gerado aleatoriamente pelo Faker

## Comandos Úteis

```bash
# Ver admins no banco
php artisan tinker
>>> \App\Models\Admin::count()
>>> \App\Models\Admin::all()

# Limpar todos os admins exceto super admin
>>> \App\Models\Admin::where('is_super_admin', false)->delete()

# Criar admins rapidamente
>>> \App\Models\Admin::factory()->count(50)->create()
```

## Boas Práticas

1. ✅ **Produção**: Use apenas seeders essenciais (Super Admin + Roles + Permissions)
2. ✅ **Desenvolvimento**: Use factories para criar dados de teste
3. ✅ **Testes**: Sempre use `RefreshDatabase` e factories
4. ✅ **Nunca** commite seeders que criam muitos dados fake
5. ✅ **Documente** as credenciais padrão no README

## Performance

Para criar muitos registros rapidamente:

```php
// Mais rápido (insere em batch)
Admin::factory()->count(1000)->create();

// Mais lento (um por vez)
for ($i = 0; $i < 1000; $i++) {
    Admin::factory()->create();
}
```

---

**Nota**: O `AdminSeeder` agora cria apenas o Super Admin. Para dados de teste, use factories!

