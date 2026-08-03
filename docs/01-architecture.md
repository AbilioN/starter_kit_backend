# Backend Architecture

## Stack
- Laravel 12, PHP 8.2
- MySQL 8 (Docker service `db`)
- Redis (cache, queues, feature flags)
- Pusher (real-time broadcasting)
- Laravel Horizon (queue dashboard)
- Docker Compose — `localhost:8006`

## Clean Architecture Layers

```
Presentation  (app/Http/)
  │  Controllers, Requests, Middleware, routes/api.php
  │  Controllers call UseCases only — no Eloquent, no repos directly
  ▼
Application   (app/Application/)
  │  UseCases — orchestrate one operation each
  │  DTOs — transport data between layers
  │  AdminFactory — bridges Eloquent model → Domain entity
  ▼
Domain        (app/Domain/)
  │  Entities — pure PHP, no Laravel dependency
  │  Repository interfaces — contracts only
  │  Service interfaces — contracts only
  │  Domain exceptions
  ▼
Infrastructure (app/Infrastructure/)
     Repository implementations — Eloquent queries
     Service implementations — Auth, Email, Storage
     
app/Models/ — Eloquent models (belong to Infrastructure layer logically)
```

## Adding a New Feature — Checklist

1. **Migration** → `database/migrations/tenant/` for tenant-scoped features (the vast majority), `database/migrations/landlord/` for platform-level (GodAdmin/Tenant/SubscriptionPlan) features — see `03-multitenancy-plan.md`
2. **Eloquent Model** → `app/Models/` (tenant-scoped models need no explicit connection — they resolve via the `tenant` connection once `IdentifyTenant` flips `database.default`; landlord models set `protected $connection = 'landlord';` explicitly)
3. **Domain Entity** → `app/Domain/Entities/`
4. **Repository Interface** → `app/Domain/Repositories/`
5. **Repository Implementation** → `app/Infrastructure/Repositories/`
6. **Bind in ServiceProvider** → `app/Providers/AppServiceProvider.php`
7. **Use Cases** → `app/Application/UseCases/<Feature>/`
8. **DTOs** → `app/Application/DTOs/<Feature>/` (if response needs shaping)
9. **Form Requests** → `app/Http/Requests/`
10. **Controller** → `app/Http/Controllers/Api/<Group>/`
11. **Routes** → `routes/api.php`
12. **Pusher event** (if real-time) → `app/Events/`
13. **Broadcast channel auth** (if new channel) → `routes/channels.php`
14. **Audit** (if action should be logged) → call `LogAuditUseCase` inside the use case

## API Response Conventions

```php
// Success read/update
return response()->json(['success' => true, 'data' => $dto]);

// Success create
return response()->json(['success' => true, 'data' => $dto], 201);

// Async accepted (e.g. chat message queued)
return response()->json(['success' => true, 'message' => '...'], 202);

// Validation error (handled by Laravel automatically via FormRequest)
// 422 + { "message": "...", "errors": { "field": ["msg"] } }

// Unauthorized
// 401 + { "message": "Unauthorized." }
// 403 + { "message": "Forbidden." }
```

Pagination shape (all list endpoints):
```json
{
  "success": true,
  "data": [...],
  "meta": {
    "total": 100, "per_page": 15, "current_page": 1,
    "last_page": 7, "from": 1, "to": 15
  }
}
```

## Auth Pattern (Admin controllers)

```php
$admin = AdminFactory::createFromModel($request->user());
$this->authorizeAction->execute($admin, 'resource-action');
```

## Permission Slugs

Format: `<resource>-<action>`

| Resource | Actions available |
|---|---|
| `admin` | create, read, update, delete, manage |
| `user` | create, read, update, delete |
| `chat` | read, manage |
| `role` | create, read, update, delete, manage, assign, unassign |
| `audit` | read |
| `notification` | read |
| `setting` | read, update |
| `file` | read, create, delete |

## Pusher Channel Architecture

```
personal:  private-user.user.{userId}   ← events for a regular user
           private-user.admin.{adminId} ← events for an admin
per-chat:  private-chat.{chatId}        ← typing indicators only
```

Fan-out on `MessageSent`: the backend iterates all chat participants and broadcasts to each personal channel — no chat channel subscription needed for message delivery.

## Key Files

| File | Purpose |
|---|---|
| `routes/api.php` | All routes (public, user Sanctum, admin Sanctum) |
| `routes/channels.php` | Pusher channel auth callbacks |
| `app/Providers/AppServiceProvider.php` | Interface → implementation bindings |
| `app/Http/Middleware/AdminAuthMiddleware.php` | Verifies request user is an Admin model |
| `app/Application/Services/AdminFactory.php` | Eloquent Admin → Domain Admin entity |
| `app/Helpers/Settings.php` | `Settings::get('key')`, `Settings::isEnabled('features.chat')` |
| `database/seeders/` | Permission seeder, super-admin seeder |

## Database Tables (current)

| Table | Description |
|---|---|
| `users` | End users (mobile) |
| `admins` | Admin panel users |
| `email_verifications` | Codes for email verification flow |
| `personal_access_tokens` | Sanctum tokens (users + admins) |
| `roles` | Named roles |
| `permissions` | Named permission slugs |
| `role_permissions` | Role ↔ Permission pivot |
| `admin_roles` | Admin ↔ Role pivot |
| `chats` | Chat rooms (type: private/group) |
| `messages` | Chat messages |
| `chat_user` | Chat ↔ Participant pivot (`user_type` is source of truth) |
| `audit_logs` | Immutable event log |
| `notifications` | DB notifications |
| `files` | File metadata |
| `settings` | Key-value app settings |
| `assistants` | AI assistant configs |
