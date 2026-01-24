# Exemplo de Implementação de Paginação

Este documento mostra como implementar paginação nas camadas: Repository → UseCase → Controller → Route

## 1. Interface do Repositório (Domain Layer)

```php
<?php
// app/Domain/Repositories/AdminRepositoryInterface.php

namespace App\Domain\Repositories;

use App\Domain\Entities\Admin;

interface AdminRepositoryInterface
{
    // Método existente
    public function findAll(): array;
    
    // Novo método com paginação
    public function findAllPaginated(int $page = 1, int $perPage = 15): array;
    
    // Outros métodos...
}
```

**Retorno esperado do `findAllPaginated`:**
```php
[
    'data' => [...],      // Array de entidades Admin
    'total' => 100,       // Total de registros
    'per_page' => 15,     // Itens por página
    'current_page' => 1,  // Página atual
    'last_page' => 7,     // Última página
    'from' => 1,          // Primeiro item da página
    'to' => 15            // Último item da página
]
```

---

## 2. Implementação do Repositório (Infrastructure Layer)

```php
<?php
// app/Infrastructure/Repositories/AdminRepository.php

namespace App\Infrastructure\Repositories;

use App\Domain\Entities\Admin;
use App\Domain\Repositories\AdminRepositoryInterface;
use App\Models\Admin as AdminModel;

class AdminRepository implements AdminRepositoryInterface
{
    // Método existente
    public function findAll(): array
    {
        $admins = AdminModel::all();
        
        return $admins->map(function ($admin) {
            return $admin->toEntity();
        })->toArray();
    }

    // Novo método com paginação
    public function findAllPaginated(int $page = 1, int $perPage = 15): array
    {
        $paginator = AdminModel::orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);
        
        return [
            'data' => $paginator->items()->map(fn($admin) => $admin->toEntity())->toArray(),
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ];
    }
    
    // Outros métodos...
}
```

**Opções avançadas:**

```php
// Com filtros
public function findAllPaginated(
    int $page = 1, 
    int $perPage = 15,
    ?string $search = null,
    ?bool $isActive = null
): array {
    $query = AdminModel::query();
    
    // Aplicar filtros
    if ($search) {
        $query->where(function($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('email', 'like', "%{$search}%");
        });
    }
    
    if ($isActive !== null) {
        $query->where('is_active', $isActive);
    }
    
    $paginator = $query->orderBy('created_at', 'desc')
        ->paginate($perPage, ['*'], 'page', $page);
    
    return [
        'data' => $paginator->items()->map(fn($admin) => $admin->toEntity())->toArray(),
        'total' => $paginator->total(),
        'per_page' => $paginator->perPage(),
        'current_page' => $paginator->currentPage(),
        'last_page' => $paginator->lastPage(),
        'from' => $paginator->firstItem(),
        'to' => $paginator->lastItem(),
    ];
}
```

---

## 3. Use Case (Application Layer)

```php
<?php
// app/Application/UseCases/Admin/GetAllAdminsUseCase.php

namespace App\Application\UseCases\Admin;

use App\Domain\Repositories\AdminRepositoryInterface;

class GetAllAdminsUseCase
{
    public function __construct(
        private AdminRepositoryInterface $adminRepository
    ) {}

    // Método existente (sem paginação)
    public function execute(): array
    {
        $admins = $this->adminRepository->findAll();
        
        return array_map(function ($admin) {
            return $admin->toDto()->toArray();
        }, $admins);
    }

    // Novo método com paginação
    public function executePaginated(
        int $page = 1, 
        int $perPage = 15,
        ?string $search = null,
        ?bool $isActive = null
    ): array {
        $result = $this->adminRepository->findAllPaginated($page, $perPage, $search, $isActive);
        
        // Converter entidades para DTOs
        $result['data'] = array_map(function ($admin) {
            return $admin->toDto()->toArray();
        }, $result['data']);
        
        return $result;
    }
}
```

**Alternativa: Criar UseCase separado**

```php
<?php
// app/Application/UseCases/Admin/GetPaginatedAdminsUseCase.php

namespace App\Application\UseCases\Admin;

use App\Domain\Repositories\AdminRepositoryInterface;

class GetPaginatedAdminsUseCase
{
    public function __construct(
        private AdminRepositoryInterface $adminRepository
    ) {}

    public function execute(
        int $page = 1, 
        int $perPage = 15,
        ?string $search = null,
        ?bool $isActive = null
    ): array {
        $result = $this->adminRepository->findAllPaginated($page, $perPage, $search, $isActive);
        
        // Converter entidades para DTOs
        $result['data'] = array_map(function ($admin) {
            return $admin->toDto()->toArray();
        }, $result['data']);
        
        return $result;
    }
}
```

---

## 4. Controller (Presentation Layer)

```php
<?php
// app/Http/Controllers/Api/Admin/AdminController.php

namespace App\Http\Controllers\Api\Admin;

use App\Application\Services\AdminFactory;
use App\Application\UseCases\Admin\Authorization\AuthorizeActionUseCase;
use App\Application\UseCases\Admin\GetAllAdminsUseCase;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function __construct(
        private GetAllAdminsUseCase $getAllAdminsUseCase,
        private AuthorizeActionUseCase $authorizeActionUseCase
        // Outros use cases...
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $adminModel = $request->user();
            $admin = AdminFactory::createFromModel($adminModel);
            $this->authorizeActionUseCase->execute($admin, 'admin-read');
            
            // Obter parâmetros de paginação da query string
            $page = (int) $request->query('page', 1);
            $perPage = (int) $request->query('per_page', 15);
            $search = $request->query('search');
            $isActive = $request->query('is_active');
            
            // Converter string 'true'/'false' para boolean
            if ($isActive !== null) {
                $isActive = filter_var($isActive, FILTER_VALIDATE_BOOLEAN);
            }
            
            // Validar limites
            $perPage = min(max($perPage, 1), 100); // Entre 1 e 100
            $page = max($page, 1);
            
            // Executar use case
            $result = $this->getAllAdminsUseCase->executePaginated(
                $page, 
                $perPage,
                $search,
                $isActive
            );
            
            return response()->json([
                'success' => true,
                'data' => $result['data'],
                'pagination' => [
                    'total' => $result['total'],
                    'per_page' => $result['per_page'],
                    'current_page' => $result['current_page'],
                    'last_page' => $result['last_page'],
                    'from' => $result['from'],
                    'to' => $result['to']
                ]
            ], 200);
        } catch (\App\Domain\Exceptions\AuthorizationException $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 403);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Internal server error: ' . $e->getMessage()
            ], 500);
        }
    }
}
```

---

## 5. Rota (Routes Layer)

A rota continua a mesma, mas agora aceita query parameters:

```php
<?php
// routes/api.php

Route::middleware(['auth:sanctum', 'admin.auth'])->group(function () {
    // GET /api/admin/admins?page=1&per_page=15&search=john&is_active=true
    Route::get('/admins', [AdminController::class, 'index']);
    
    // Outras rotas...
});
```

---

## 6. Exemplos de Uso da API

### Request básico (página 1, 15 itens)
```bash
GET /api/admin/admins
Authorization: Bearer {token}
```

### Request com paginação
```bash
GET /api/admin/admins?page=2&per_page=20
Authorization: Bearer {token}
```

### Request com busca
```bash
GET /api/admin/admins?search=john&page=1&per_page=10
Authorization: Bearer {token}
```

### Request com filtro
```bash
GET /api/admin/admins?is_active=true&page=1&per_page=25
Authorization: Bearer {token}
```

### Request completo
```bash
GET /api/admin/admins?page=2&per_page=20&search=admin&is_active=true
Authorization: Bearer {token}
```

---

## 7. Resposta da API

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Admin Name",
      "email": "admin@example.com",
      "is_active": true,
      "created_at": "2025-10-09T10:00:00.000000Z"
    },
    // ... mais admins
  ],
  "pagination": {
    "total": 100,
    "per_page": 15,
    "current_page": 1,
    "last_page": 7,
    "from": 1,
    "to": 15
  }
}
```

---

## 8. Abordagem Alternativa: Usar LengthAwarePaginator

Se preferir retornar o objeto paginator diretamente:

```php
// No Repository
public function findAllPaginated(int $page = 1, int $perPage = 15)
{
    return AdminModel::orderBy('created_at', 'desc')
        ->paginate($perPage, ['*'], 'page', $page);
}

// No UseCase
public function executePaginated(int $page = 1, int $perPage = 15)
{
    return $this->adminRepository->findAllPaginated($page, $perPage);
}

// No Controller
public function index(Request $request): JsonResponse
{
    // ... autorização ...
    
    $paginator = $this->getAllAdminsUseCase->executePaginated($page, $perPage);
    
    // Converter para DTOs
    $data = $paginator->through(function ($admin) {
        return $admin->toEntity()->toDto()->toArray();
    });
    
    return response()->json([
        'success' => true,
        'data' => $data->items(),
        'pagination' => [
            'total' => $data->total(),
            'per_page' => $data->perPage(),
            'current_page' => $data->currentPage(),
            'last_page' => $data->lastPage(),
        ]
    ]);
}
```

---

## 9. Considerações

### Vantagens da Paginação Manual (Array)
- ✅ Mais controle sobre a estrutura de retorno
- ✅ Independente do framework (DDD puro)
- ✅ Fácil de testar
- ✅ Explícito sobre o que retorna

### Vantagens do LengthAwarePaginator
- ✅ Menos código
- ✅ Integração nativa com Laravel
- ✅ Métodos úteis do paginator disponíveis
- ✅ Mais rápido de implementar

### Recomendação
Para um projeto com DDD bem estruturado, prefira **retornar arrays** para manter as camadas desacopladas do framework.

---

## 10. Testes

```php
<?php
// tests/Feature/Admin/AdminPaginationTest.php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPaginationTest extends TestCase
{
    use RefreshDatabase;

    private string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();
        
        $admin = Admin::factory()->create();
        $this->adminToken = $admin->createToken('test')->plainTextToken;
    }

    public function test_can_get_paginated_admins()
    {
        Admin::factory()->count(30)->create();
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken
        ])->getJson('/api/admin/admins?page=1&per_page=10');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'name', 'email']
                ],
                'pagination' => [
                    'total',
                    'per_page',
                    'current_page',
                    'last_page',
                    'from',
                    'to'
                ]
            ])
            ->assertJson([
                'pagination' => [
                    'per_page' => 10,
                    'current_page' => 1,
                    'last_page' => 4 // 30 admins + 1 seeded / 10 per page
                ]
            ]);
    }

    public function test_can_search_admins_with_pagination()
    {
        Admin::factory()->create(['name' => 'John Doe']);
        Admin::factory()->create(['name' => 'Jane Smith']);
        Admin::factory()->count(10)->create();
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken
        ])->getJson('/api/admin/admins?search=John&page=1&per_page=10');
        
        $response->assertStatus(200)
            ->assertJsonPath('pagination.total', 1);
    }
}
```


