# Admin API Documentation

## Overview
Esta documentação descreve as rotas da API para autenticação e gerenciamento de administradores.

## Base URL
```
http://localhost:8000/api
```

## Endpoints

### 1. Admin Login
**POST** `/admin/login`

Autentica um administrador e retorna um token de acesso.

**Request Body:**
```json
{
    "email": "admin3@dashboard.com",
    "password": "password123"
}
```

**Response (200):**
```json
{
    "admin": {
        "id": 1,
        "name": "Super Admin",
        "email": "admin3@dashboard.com",
        "is_active": true,
        "last_login_at": "2025-06-27 20:45:30"
    },
    "token": "1|abc123def456..."
}
```

**Response (401):**
```json
{
    "message": "Invalid credentials"
}
```

### 2. Admin Register
**POST** `/admin/register`

Registra um novo administrador.

**Request Body:**
```json
{
    "name": "New Admin",
    "email": "newadmin3@dashboard.com",
    "password": "password123",
    "password_confirmation": "password123"
}
```

**Response (201):**
```json
{
    "admin": {
        "id": 2,
        "name": "New Admin",
        "email": "newadmin3@dashboard.com",
        "is_active": true,
        "created_at": "2025-06-27 20:45:30"
    }
}
```

**Response (422):**
```json
{
    "message": "Admin with this email already exists"
}
```

### 3. Admin Dashboard (Protected)
**GET** `/admin/dashboard`

Acessa o dashboard de administrador (requer autenticação).

**Headers:**
```
Authorization: Bearer {token}
```

**Response (200):**
```json
{
    "message": "Welcome to Admin Dashboard",
    "data": {
        "total_users": 0,
        "total_admins": 1,
        "system_status": "active"
    }
}
```

**Response (401):**
```json
{
    "message": "Unauthorized"
}
```

**Response (403):**
```json
{
    "message": "Access denied. Admin privileges required."
}
```

## Autenticação

Para acessar rotas protegidas, inclua o token no header Authorization:

```
Authorization: Bearer {token}
```

## Estrutura do Projeto

### Domain Layer
- `app/Domain/Entities/Admin.php` - Entidade Admin
- `app/Domain/Repositories/AdminRepositoryInterface.php` - Interface do repositório
- `app/Domain/Services/AdminAuthServiceInterface.php` - Interface do serviço de autenticação

### Application Layer
- `app/Application/UseCases/Auth/AdminLoginUseCase.php` - Use case de login
- `app/Application/UseCases/Auth/AdminRegisterUseCase.php` - Use case de registro

### Infrastructure Layer
- `app/Infrastructure/Repositories/AdminRepository.php` - Implementação do repositório
- `app/Infrastructure/Services/AdminAuthService.php` - Implementação do serviço de autenticação

### HTTP Layer
- `app/Http/Controllers/Api/Auth/AdminLoginController.php` - Controller de login
- `app/Http/Controllers/Api/Auth/AdminRegisterController.php` - Controller de registro
- `app/Http/Controllers/Api/Admin/DashboardController.php` - Controller do dashboard
- `app/Http/Requests/AdminLoginRequest.php` - Validação de login
- `app/Http/Requests/AdminRegisterRequest.php` - Validação de registro
- `app/Http/Middleware/AdminAuthMiddleware.php` - Middleware de autenticação de admin

### Model
- `app/Models/Admin.php` - Model Eloquent

## Dados de Teste

Um administrador padrão é criado automaticamente:

- **Email:** admin3@dashboard.com
- **Password:** password123
- **Status:** Ativo

## Middleware

O middleware `admin.auth` verifica se o usuário autenticado é um administrador ativo. Para usar em rotas protegidas:

```php
Route::middleware(['auth:sanctum', 'admin.auth'])->group(function () {
    // Rotas protegidas de admin
});
``` 