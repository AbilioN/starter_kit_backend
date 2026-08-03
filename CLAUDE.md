# Claude Coding Guide (starter_kit_backend)

This repository is a **Laravel 12** backend (PHP 8.2) designed as a **BtoB starter kit** with **Clean Architecture + DDD-ish layering**. Follow the conventions below when making changes.

## Architecture (non-negotiable boundaries)

### Layers and where code lives
- **Presentation / HTTP**: `app/Http/Controllers/**`, `app/Http/Requests/**`, `app/Http/Middleware/**`, `routes/*.php`
- **Application**: `app/Application/**`
  - Use cases: `app/Application/UseCases/**`
  - DTOs: `app/Application/DTOs/**`
  - “Factories” bridging Eloquent → Domain entities: `app/Application/Services/**` (e.g. `AdminFactory`)
- **Domain**: `app/Domain/**`
  - Entities: `app/Domain/Entities/**`
  - Repository interfaces: `app/Domain/Repositories/**`
  - Service interfaces: `app/Domain/Services/**`
  - Domain exceptions: `app/Domain/Exceptions/**`
- **Infrastructure**: `app/Infrastructure/**`
  - Repository implementations: `app/Infrastructure/Repositories/**`
  - Service implementations: `app/Infrastructure/Services/**`
- **Eloquent models**: `app/Models/**`
- **DB schema/seed**: `database/migrations/**`, `database/seeders/**`, `database/factories/**`

### Dependency direction
- Controllers depend on **use cases**.
- Use cases depend on **domain interfaces** (repositories/services) and return **domain entities** or simple arrays/DTOs.
- Infrastructure implements domain interfaces and is allowed to touch Eloquent/DB.
- **Repositories should NOT return DTOs.** They return **domain entities** (or arrays that are strictly internal to infra and converted upward).

## API and response conventions

### Routes
Primary API routes are in `routes/api.php` and include:
- **Public auth**: `/api/login`, `/api/register`, `/api/verify-email`, `/api/resend-verification-code`
- **Chat (Sanctum)**: `/api/chat/*`, `/api/chats`, `/api/chat/{chatId}/messages`, etc.
- **Admin auth**: `/api/admin/login`, `/api/admin/register`
- **Admin protected**: `/api/admin/*` guarded by `auth:sanctum` + `admin.auth`

### AuthZ pattern (admin)
Controllers commonly:
1. Convert current admin model → domain entity via `AdminFactory::createFromModel($request->user())`
2. Enforce permission via `AuthorizeActionUseCase->execute($admin, '<permission-slug>')`

Permission slugs follow `<resource>-<action>` (e.g. `admin-read`, `role-manage`, `audit-read`).

### Audit logs are IMMUTABLE
Audit endpoints are **read-only** by design.
- Only permission: `audit-read`
- No delete/edit endpoints (even super admin should not be able to mutate logs)
See `app/Http/Controllers/Api/Admin/AuditController.php` and docs under `docs/AUDIT_*.md`.

### Chat: type derivation rules
The **source of truth** for participant type is `chat_user.user_type`.
- `messages.sender_type` and `chats.created_by_type` were removed/should not be relied on.
- When returning chat messages, enrich/derive `sender_type` from `chat_user` (see `GetChatMessagesUseCase`).

### HTTP status codes (observed patterns)
- 200 OK for successful reads/updates
- 201 Created for resource creation
- 202 Accepted for “queued/async” chat message processing (chat send-to-chat endpoint)
- 401 for unauthenticated
- 403 for authenticated but unauthorized (permission denied / admin.auth)
- 422 for validation errors

Keep responses consistent with existing endpoints:
- Many admin endpoints use `{ "success": true, "data": ... }`
- Some auth endpoints return `{ user: ..., token: ... }` or `{ admin: ..., token: ..., roles: ... }`

When extending existing endpoints, **match the existing shape** for that subsystem instead of inventing a new one.

## Database and environment

### Runtime database
Runtime is **MySQL** (Docker service `db`, MySQL 8).
Use `.env` values consistent with `.env.example`:
- `DB_CONNECTION=mysql`
- `DB_HOST=db`
- `DB_PORT=3306`
- `DB_DATABASE=starter_kit_backend`
- `DB_USERNAME=starter_kit_backend`
- `DB_PASSWORD=password`

### Tests database
PHPUnit is configured to use **SQLite** inside the container:
- `DB_CONNECTION=sqlite`
- `DB_DATABASE=/var/www/storage/app/sqlite/testing.sqlite` (see `phpunit.xml`)

Tests should be hermetic and should not require local host DB state.

## How to run (Docker)

From repo root:

```bash
# build + start
sudo docker compose up -d --build

# one-time: grant the app's DB user privileges to create/use arbitrary
# databases (database-per-tenant needs this; MYSQL_DATABASE only ever
# creates the legacy single DB_DATABASE) - docker-setup.sh does this
# automatically on a fresh setup
sudo docker compose exec db mysql -uroot -ppassword \
    -e "GRANT ALL PRIVILEGES ON *.* TO 'starter_kit_backend'@'%'; FLUSH PRIVILEGES;"

# landlord DB: always exists, migrate it directly (there is no single
# "the" runtime database anymore - see docs/03-multitenancy-plan.md)
sudo docker compose exec db mysql -uroot -ppassword \
    -e "CREATE DATABASE IF NOT EXISTS starter_kit_landlord;"
sudo docker compose exec app php artisan migrate --database=landlord --path=database/migrations/landlord --force
sudo docker compose exec app php artisan db:seed --class=GodAdminSeeder --force

# tenant DBs are created on demand, never via a blanket migrate:fresh
sudo docker compose exec app php artisan tenant:provision "Tenant A" tenant-a \
    --admin-email=owner@tenant-a.test --admin-password=password123

# run all tests (PHPUnit w/ sqlite from phpunit.xml)
sudo docker compose exec app php artisan test

# run a subset
sudo docker compose exec app php artisan test --filter=AdminsTest
```

See `docs/04-local-dev-tenants.md` for subdomain resolution setup (`/etc/hosts` or `nip.io`) needed to actually hit a provisioned tenant.

## Testing conventions

### Test types and locations
- Feature: `tests/Feature/**` (HTTP-level, hits DB)
- Integration: `tests/Integration/**`
- Unit: `tests/Unit/**` (entities/use cases/services)

### Common setup
Many admin feature tests seed in this order:
1. `RoleSeeder`
2. `PermissionSeeder`
3. `AdminSeeder` (creates super admin)
4. `AdminRolePermissionSeeder`

Prefer tests to **create only the data they need** (factories) and avoid heavy global seeding.

### Assertions
- Use `assertJsonStructure()` and `assertJsonPath()` for API responses.
- For permissions tests, existing pattern removes a permission from a role and expects 403 with an exact error string.

## Coding style expectations

### Type safety
- Use strict typing via parameter/return type hints.
- Prefer **DTOs / value objects** over “random arrays” for complex structures (see `docs/php_typing_strategies.md`).
- If a domain entity has a DTO, tests that assert `toArray()` must include all expected fields (see `docs/TEST_FIXES_ADMIN_DTO.md`).

### Validation
- Prefer Laravel `FormRequest` classes (`app/Http/Requests/**`) where they already exist for an endpoint.
- If adding a new endpoint, consider adding a `FormRequest` instead of inline `$request->validate()` unless existing controllers in that area use inline validation.

### Repositories
- Avoid bulk `Model::where(...)->update()` / `delete()` for entities that use audit traits; bulk ops bypass Eloquent events and can break automatic audit logging.

## Known hotspots / consistency notes

- There are multiple chat controllers (`app/Http/Controllers/Api/Chat/ChatController.php`, `app/Http/Controllers/Api/ChatController.php`, `app/Http/Controllers/Api/Admin/ChatController.php`) with overlapping responsibilities. When changing chat behavior, confirm which route/controller is actually used by `routes/api.php` (currently uses `App\Http\Controllers\Api\Chat\ChatController`).
- `AdminFactory` is the canonical bridge for admin model → domain authorizable user (`docs/ADMINFACTORY_RENAME.md`).
- Pagination is implemented for admins and users; keep pagination metadata keys consistent (`current_page`, `per_page`, `total`, `last_page`, `from`, `to`) and validate `per_page` between 1 and 100 (see admin controller and pagination docs).

## When implementing new features

### Preferred workflow
1. Update/extend **domain interfaces/entities** if needed.
2. Implement/extend infra repositories/services.
3. Add/extend use case(s) with unit tests where feasible.
4. Add/extend controller endpoints + feature tests.
5. Update docs in `docs/` if you change API behavior or add a new subsystem.

### Keep modules removable
This repo is evolving into a modular starter kit. Avoid tight coupling between optional modules (e.g. chat) and core RBAC/auth flows. If you introduce a “core” dependency, document it in `docs/STARTER_KIT_STRATEGY.md`.

