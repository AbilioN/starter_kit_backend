# Multitenancy Plan (GodAdmin / Tenants / Subscription Plans)

> Status: **Sprint 0 implemented and passing (28/28 dedicated tests green; full backend suite 259/259 green as of 2026-08-06)**. Read this fully before writing any code — it defines the architectural boundaries (landlord vs tenant) that sit on top of the existing Clean Architecture layers described in `01-architecture.md`. Origin: cross-repo planning in the root `starter_kit/docs/05-multitenancy-plan.md` — this file is the backend-scoped, actionable version of that plan.

## Current status (2026-08-06)

All of 0.1–0.5 below are built and tested (`php artisan test --filter="Tenant|GodAdmin"` → 28 passed, 95 assertions). Frontend (`starter_kit_frontend`) has also already wired: `useTenantTheme`/`useTenantSettings` composables, `TenantService`/`TenantRepository`, subdomain-or-`?tenant=` resolution in `utils/tenant.ts`, and a branding + subscription-plan tab on `/settings` gated by `admin.is_tenant_owner`.

**Note on naming**: the actor originally called "Sudo Admin" in this doc's first draft was renamed to **Tenant Owner** (`admins.is_tenant_owner`, not `is_sudo`) during implementation — reflected throughout below.

**Regression found and fixed (2026-08-06)**: running the entire test suite (not just the tenant-filtered subset) originally surfaced 27 failures unrelated to Sprint 0 itself, all now fixed. Full backend suite is green: **259 passed**. Root causes and fixes:
- **Int/string id mismatch**: `Admin`/`Role`/`User`/`Assistant`/`Setting` use UUID (or ULID-backed) ids, but several Application-layer use cases, repository interfaces/implementations, a controller method signature, and two FormRequest validation rules (`role_id`/`id` → `integer`) still assumed `int`. Widened to `string` throughout: `GetNotificationsUseCase`, `MarkNotificationAsReadUseCase`, `NotificationRepository(Interface)`, `AttachPermissionsToRoleUseCase`, `UpdateRoleUseCase`, `DeleteRoleUseCase`, `UpdatePermissionsToRoleUseCase`, `RoleRepository(Interface)`, `UserRepository(Interface)`, `GetUserUseCase`, `UserController::show`, `AssistantRepository(Interface)`, `Setting` entity/DTO, `AssignRoleToAdminUseCase`, `UpdateAdminUseCase`, `DeleteAdminUseCase`, `CreateAdminRequest`, `UpdateAdminRequest`, `AdminController::delete`'s inline validation.
- **Missing `AuthorizationException` catch**: `SettingController`, `FileController`, and `AuditController` only caught the generic `\Exception`, so a permission-denied `AuthorizationException` (which extends `Exception`) fell through to the generic 500 handler instead of returning 403. Added an explicit `catch (AuthorizationException $e)` → 403 branch to every method that calls `authorizeAction`/`authorizeActionUseCase`.
- **Stale test fixtures/assertions**: `RoleManagementTest::an_admin_can_create_a_role_with_permissions` hardcoded integer permission ids (`[1,2,3]`) — now fetches real `Permission::limit(3)->pluck('id')`. `LoginUseCaseTest` asserted a response shape missing the `channel` field and an `int` id. `AdminAuthMiddlewareTest` asserted the old placeholder dashboard message instead of the real `{success: true, ...}` shape. `AdminsTest`'s delete tests used `assertDatabaseMissing` instead of `assertSoftDeleted` (Admin has used `SoftDeletes` since the Sprint 1.5 migration — deletes don't actually remove the row). `ChatControllerWithChatUserTest::test_can_get_unread_count_using_chat_user_abstraction` asserted a hardcoded `unread_count: 0` with a comment accepting "current system behavior" — the endpoint now (or already did) correctly count the 2 unread messages the test itself creates; updated to assert `2`.
- **Missing `libjpeg` support in the `gd` PHP extension**: `Dockerfile` installed `gd` without `libjpeg-dev`/`--with-jpeg`, so `imagejpeg()` didn't exist and `UploadedFile::fake()->image()` (used by `FileTest`) threw `LogicException`. Added `libjpeg62-turbo-dev` + `docker-php-ext-configure gd --with-jpeg` and rebuilt the `app` image.

**Not fixed / deliberately left alone**: `AuditController`'s `show`/`modelHistory`/`userActivity` methods still type-hint route ids as `int` (same class of bug, but `AuditLog`'s id lookup path has no test coverage today, and Audit is LGPD-sensitive — fix this with a test in hand, not blind). `PermissionRepositoryInterface::update/delete/attachRoles/detachRoles` still type-hint `int $id` — currently dead code (no controller calls them), same reasoning.

---

## Why

The kit is moving from a single-tenant BtoB starter to a white-label multi-tenant SaaS platform. A new actor sits above today's Admin/User: the **GodAdmin**, who runs the platform itself — creates tenants, defines subscription plans (no payment processing yet). Each tenant gets a **fully isolated MySQL database** — not a shared schema with a `tenant_id` column.

This is the prerequisite foundation for the CRM SaaS fork mentioned in the root docs' strategic direction.

---

## Locked architectural decisions

These were decided explicitly and should not be revisited without a strong reason (mirrors `starter_kit/docs/03-decisions.md`):

1. **Database-per-tenant**, not shared schema with `tenant_id` — full data isolation between tenants.
2. **Tenant resolution by subdomain** (`tenant-a.app.test`), read from the `Host` header. No custom-domain support yet (candidate for a later phase).
3. **No third-party tenancy package** (explicitly not using `stancl/tenancy` or similar) — connection switching is hand-rolled: `config(['database.connections.tenant.database' => ...])` + `DB::purge('tenant')`.
4. **GodAdmin is a separate actor**: Laravel + **Livewire**, session-based auth on its own guard, lives in a fixed `landlord` DB. Never Sanctum, never reachable through Nuxt.
5. `lets_jam_app` (Flutter) is **explicitly out of scope** for this phase — mobile stays pointed at a single fixed tenant. Don't build tenant-selection logic for it now.
6. No real data migration needed — this is a clean redesign, no existing production tenant data to preserve.

---

## Actors

| Actor | Logs in via | DB connection | Scope |
|---|---|---|---|
| **GodAdmin** | Laravel + Livewire, own session guard | `landlord` | Creates tenants, creates/edits Subscription Plans, sets tenant branding, suspends tenants |
| **Tenant Owner** | Nuxt (Sanctum — same login as today's Admin) | `tenant` | The tenant's owner (`admins.is_tenant_owner = true`). Picks/changes the tenant's Subscription Plan, edits tenant branding. Otherwise a normal Admin. |
| **Admin** (existing) | Nuxt (Sanctum) | `tenant` | Unchanged — existing RBAC, scoped entirely to their tenant's database |
| **User** (existing) | Flutter | `tenant` | Unchanged, single fixed tenant this phase |

---

## Landlord database (single, fixed — e.g. `starter_kit_landlord`)

New Eloquent models, each with explicit `protected $connection = 'landlord';`, living in `app/Models/` per existing convention but logically belonging to a new `Landlord` domain slice:

- **`godadmins`** — id, name, email, password, remember_token, timestamps
- **`subscription_plans`** — id, name, slug, price_cents (nullable, no billing logic yet), features (json — mirrors existing `Settings` feature flags: `chat`, `file_upload`, `notifications`, `ai_agent`), limits (json — `max_admins`, `max_users`, `max_storage_mb`), is_active, timestamps
- **`tenants`** — id (uuid), name, subdomain (unique), database_name (unique), subscription_plan_id (FK, nullable), theme_primary_color, theme_secondary_color, logo_path, status (`pending`|`active`|`suspended`), created_via (`godadmin`|`self_service`), timestamps
- **`landlord_audit_logs`** — id, actor_type (`godadmin`), actor_id, action, model, model_id, metadata (json), created_at — immutable, same pattern as the existing `HasAuditLog` trait, just pointed at the `landlord` connection

The landlord DB never stores tenant business data (no cached Tenant Owner name/email) — tenant DBs stay the single source of truth for their own data.

---

## Tenant database (one physical MySQL database per tenant, same server)

Same schema shipped today (`users`, `admins`, `roles`, `permissions`, `role_permissions`, `admin_roles`, `chats`, `messages`, `chat_user`, `audit_logs`, `files`, `settings`, `notifications`, `assistants`, `personal_access_tokens` — see the table in `01-architecture.md`), plus:

- `admins.is_tenant_owner` (boolean, default false) — set true on the first Admin created during provisioning

All existing Eloquent models need an explicit `protected $connection = 'tenant';` — introduce a shared trait (e.g. `BelongsToTenantConnection`) applied to every tenant-scoped model so nothing accidentally queries the wrong connection when the default connection changes.

---

## Connection switching (custom, no package)

1. `config/database.php`: add a fixed `landlord` connection, and a `tenant` connection template (`database` starts `null`, filled at runtime).
2. New middleware `app/Http/Middleware/IdentifyTenant.php`:
   - Reads the `Host` header, extracts the subdomain (`tenant-a` from `tenant-a.app.test`).
   - `Tenant::where('subdomain', $sub)->where('status', 'active')->firstOrFail()` on the `landlord` connection (404 `tenant_not_found`, or 403 if suspended).
   - `config(['database.connections.tenant.database' => $tenant->database_name]); DB::purge('tenant');`
   - Binds the resolved tenant into the container: `app()->instance('currentTenant', $tenant);`
3. Apply `IdentifyTenant` to all tenant-facing `web`/`api` route groups in `routes/api.php` — **not** to the new `/god/*` routes.
4. Local dev without wildcard DNS: document `/etc/hosts` entries per tenant (e.g. `127.0.0.1 tenant-a.starterkit.test`) or a wildcard dev domain (`*.127.0.0.1.nip.io`) in this repo's README before implementation starts.

---

## Provisioning

New service class (e.g. `app/Application/UseCases/Tenant/ProvisionTenantUseCase.php`, following the existing use-case pattern) + artisan command wrapper, used by both entry points below:

1. Validate subdomain uniqueness.
2. `CREATE DATABASE {database_name}` (raw statement, bootstrap connection).
3. Insert `tenants` row on `landlord`.
4. Point the `tenant` connection at the new database, run tenant migrations (`database/migrations/tenant/`), seed baseline roles/permissions, create the first Admin with `is_tenant_owner = true`.
5. Copy the chosen Subscription Plan's `features` json into that tenant's own `settings` table (`features.*` keys) — the existing `Settings::isEnabled()` helper needs no code changes, it's just seeded from the plan.

**Two entry points, same underlying service:**
- **GodAdmin-created**: Livewire form, GodAdmin picks name/subdomain/plan directly.
- **Self-service**: a public sign-up flow served by Laravel directly (Blade/Livewire, not Nuxt — Nuxt only exists per-subdomain) where a future Tenant Owner picks subdomain/name/plan (no payment step yet), then gets redirected to `https://{subdomain}.app.test` to log in.

---

## GodAdmin app (Livewire)

- New `godadmin` guard in `config/auth.php` (session driver), provider backed by `GodAdmin` model on `landlord`.
- Routes under `/god/*` — Blade + Livewire, CSRF + session auth, no Sanctum involved.
- Components: `Login`, `Dashboard`, `SubscriptionPlans/Index`, `SubscriptionPlans/Form`, `Tenants/Index`, `Tenants/Create`, `Tenants/Show`.
- No granular RBAC in v1 — a single GodAdmin role. Flag as an open question if multiple platform operators with different access levels ever become necessary.

---

## Subscription Plan → feature gating

- Plan's `features` json seeds the tenant's `settings` table at provisioning time (see above) — reuses the existing feature-flag mechanism (`app/Helpers/Settings.php`), no new gating code inside tenant business logic.
- Plan change flow (Tenant Owner only): `PATCH /api/admin/tenant/subscription-plan`
  - Requires `is_tenant_owner` check (not the permission system — it's a single boolean, not a resource/action).
  - Writes `tenants.subscription_plan_id` on `landlord`.
  - Re-syncs `features.*` settings inside the tenant DB.
  - **Not atomic across the two connections** — accept eventual consistency, audit-log both sides, and queue a retry job if the settings re-sync fails after the landlord write succeeds.

---

## Branding

- Stored on `tenants` (landlord): `theme_primary_color`, `theme_secondary_color`, `logo_path`.
- `GET /api/tenant/theme` — **public, unauthenticated**, resolved by `IdentifyTenant`, returns `{ name, primary_color, secondary_color, logo_url }`. Consumed by Nuxt's login page before rendering, so branding shows even pre-auth.
- `PATCH /api/admin/tenant/branding` — Tenant Owner only, same cross-connection caveat as plan changes.

---

## RBAC & Audit

- **No new permission slugs** in the existing table (`admin`, `user`, `chat`, `role`, `audit`, `notification`, `setting`, `file`). Tenant-owner-only actions are gated by `admins.is_tenant_owner`, not RBAC.
- Cross-connection writes get logged on **both** sides:
  - Tenant `audit_logs`: `subscription_plan_changed`, `tenant_branding_updated` (via the existing `LogAuditUseCase` pattern)
  - Landlord `landlord_audit_logs`: `tenant_updated_by_sudo_admin` (metadata: `{tenant_id}`)
- GodAdmin actions (tenant create/suspend, plan create/edit) → `landlord_audit_logs` only.

---

## New API surface (for frontend awareness — implement here, Nuxt consumes)

| Endpoint | Auth | Notes |
|---|---|---|
| `GET /api/tenant/theme` | none (public) | Resolved via `IdentifyTenant`; branding for login page |
| `PATCH /api/admin/tenant/subscription-plan` | Sanctum + `is_tenant_owner` | Changes tenant's plan, re-syncs feature settings |
| `PATCH /api/admin/tenant/branding` | Sanctum + `is_tenant_owner` | Updates colors/logo |

GodAdmin's own CRUD (tenants, subscription plans) is Livewire-rendered server-side — no JSON API needed for those.

---

## Open questions / risks

1. ~~**Queued jobs and tenant context**~~ — **Resolved**: `app/Jobs/Middleware/EstablishTenantConnection.php`, a job middleware (not a trait, so it applies uniformly across worker types) carrying `tenant_id` and re-establishing the connection on the worker. All four existing jobs (`ProcessMessageJob`, `ProcessOpenAIRequest`, `ProcessOpenAIResponse`, `RetrySettingsSyncJob`) use it. Covered by `tests/Feature/Jobs/TenantAwareJobTest.php`.
2. ~~**Local dev DNS**~~ — **Resolved**: `docs/04-local-dev-tenants.md` documents `/etc/hosts`/wildcard-domain setup, plus a `?tenant=` query-param fallback in `IdentifyTenant` (local/testing environments only) for zero-DNS-setup dev — see `tests/Feature/Tenant/TenantQueryParamFallbackTest.php`.
3. ~~**Two-connection writes aren't transactional**~~ — **Resolved as designed** (eventual consistency, not true atomicity): `RetrySettingsSyncJob` handles the retry path when the tenant-side settings re-sync fails after the landlord write succeeds.
4. ~~**Docker**~~ — **Resolved**: no structural change was needed; tenant databases are created on the same MySQL instance via `CREATE DATABASE`.
5. ~~**Clean Architecture boundary**~~ — **Resolved pragmatically**: no separate `Landlord/*` namespace split was introduced. Landlord models (`GodAdmin`, `SubscriptionPlan`, `Tenant`, `LandlordAuditLog`) live in `app/Models/` alongside tenant models, distinguished only by their explicit `protected $connection`. Simpler than the originally-considered namespace split; revisit only if the landlord side grows substantially.
6. ~~**Testing**~~ — **Resolved**: `tests/TenantTestCase.php` gives `landlord` and `tenant` each their own SQLite file, migrated once per test process and wrapped in a rolled-back transaction per test (`$connectionsToTransact`), plus a `Host`-header override so `actingAsTenant()`/`useTenantHost()` can simulate real subdomain requests.
7. ~~**Pre-existing ID type mismatch surfaced by full test suite**~~ — **Fixed 2026-08-06**, see "Current status" above.

---

## Explicitly out of scope (this phase)

- `lets_jam_app` (Flutter) tenant-awareness.
- Real billing/payment processing on Subscription Plans.
- Custom domains per tenant (beyond `*.app.test` subdomains).
- Multiple GodAdmin roles/permissions.

---

## Roadmap — Sprint 0: Multitenancy Foundation (backend)

### 0.1 Landlord Database & Connection Switching

- [x] Add `landlord` connection to `config/database.php` (fixed DB, e.g. `starter_kit_landlord`)
- [x] Add `tenant` connection template to `config/database.php` (database filled at runtime)
- [x] Create landlord migrations: `godadmins`, `subscription_plans`, `tenants`, `landlord_audit_logs`
- [x] Create `GodAdmin`, `SubscriptionPlan`, `Tenant`, `LandlordAuditLog` models with explicit `protected $connection = 'landlord'`
- [x] Add `protected $connection = 'tenant'` (via shared trait) to all existing tenant-scoped models (Admin, User, Chat, Message, AuditLog, File, Setting, Notification, Assistant)
- [x] Create `IdentifyTenant` middleware — resolves subdomain from `Host` header, looks up active tenant on `landlord`, points `tenant` connection at `tenant.database_name`, purges connection, binds `currentTenant` in container
- [x] Apply `IdentifyTenant` to all tenant-facing `web`/`api` routes (not to `/god/*`)
- [x] Document local dev subdomain setup (`/etc/hosts` or wildcard `nip.io` domain) in this repo's README

### 0.2 Tenant Provisioning

- [x] Add `admins.is_tenant_owner` boolean column (migration, default false)
- [x] Create `ProvisionTenant` service/artisan command: validates subdomain, `CREATE DATABASE`, inserts `tenants` row, runs tenant migrations, seeds roles/permissions, creates first Admin with `is_tenant_owner = true`, seeds `settings.features.*` from the chosen plan
- [x] Wire GodAdmin-created provisioning (via Livewire form)
- [x] Wire self-service provisioning (public sign-up flow, served by Laravel — subdomain + name + plan, no payment)
- [x] Write feature tests: `TenantProvisioningTest` (both entry points)

### 0.3 GodAdmin (Livewire)

- [x] Add `godadmin` guard (session driver) in `config/auth.php`, provider backed by `GodAdmin` on `landlord`
- [x] Routes under `/god/*` (Blade + Livewire, session auth, CSRF)
- [x] `Login` component
- [x] `Dashboard` component
- [x] `SubscriptionPlans/Index` + `SubscriptionPlans/Form` (name, slug, price_cents, features json, limits json, is_active)
- [x] `Tenants/Index` + `Tenants/Create` + `Tenants/Show` (create tenant manually, view/suspend)
- [x] Write feature tests: `GodAdminAuthTest`, `SubscriptionPlanManagementTest`, `TenantManagementTest`

### 0.4 Subscription Plan Changes & Branding (Tenant Owner)

- [x] Create `PATCH /api/admin/tenant/subscription-plan` — `is_tenant_owner`-only, writes `landlord`, re-syncs tenant `settings.features.*`, audit-logs both sides
- [x] Create `PATCH /api/admin/tenant/branding` — `is_tenant_owner`-only, writes `theme_primary_color`/`theme_secondary_color`/`logo_path` on `landlord`
- [x] Create `GET /api/tenant/theme` — public, unauthenticated, resolved by `IdentifyTenant`, returns branding for the login page
- [x] Add retry/reconciliation job for failed cross-connection settings sync
- [x] Write feature tests: `SubscriptionPlanChangeTest`, `TenantBrandingTest`

### 0.5 Tenant-Aware Queues

- [x] Add `TenantAware` job trait/middleware — carries `tenant_id` in payload, re-establishes tenant connection when the job runs on a Horizon worker
- [x] Audit existing queued jobs (notifications, OpenAI processing) and apply the trait
- [x] Write test verifying a queued job executes against the correct tenant database

---

## Implementation order (as it actually happened)

0.1 → 0.2 → 0.5 → 0.3/0.4 in parallel, matching the original suggested order. Sprint 0 is complete.

## Next steps

1. ~~Fix the ID type mismatch~~ — **done 2026-08-06**, see "Current status" above.
2. ~~Stale test assertions~~ — **done 2026-08-06**.
3. ~~`FileTest` GD extension~~ — **done 2026-08-06** (`Dockerfile` now configures `gd` with `--with-jpeg`; image rebuilt).
4. **Self-service tenant signup has no form UI yet** — `POST /signup` (`TenantSignupController`) works, but nothing renders a form against it (no Blade view, no Nuxt page). GodAdmin-created provisioning is fully usable via Livewire in the meantime.
5. **Follow-up left deliberately unfixed** — `AuditController`'s `show`/`modelHistory`/`userActivity` and `PermissionRepositoryInterface`'s unused methods still type-hint ids as `int`. Same bug class, but no test covers those paths today — fix with a test in hand.
6. Once the above is addressed, move on to `starter_kit/roadmap.md` Sprint 1 (Production Readiness) or further round out multitenancy (custom domains, multiple GodAdmin roles) per the "Explicitly out of scope" list above — whichever the coordinator session prioritizes.

Update this file's checkboxes as work lands, and update `starter_kit/roadmap.md` + `starter_kit/docs/02-roadmap.md` (the cross-repo coordinator) to keep both in sync.
