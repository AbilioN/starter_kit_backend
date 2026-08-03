# Multitenancy Plan (GodAdmin / Tenants / Subscription Plans)

> Status: planned, not implemented. Read this fully before writing any code — it defines new architectural boundaries (landlord vs tenant) that sit on top of the existing Clean Architecture layers described in `01-architecture.md`. Origin: cross-repo planning in the root `starter_kit/docs/05-multitenancy-plan.md` — this file is the backend-scoped, actionable version of that plan.

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

1. **Queued jobs and tenant context** — Horizon workers need to know which tenant DB a job belongs to. Every tenant-scoped job must carry `tenant_id` in its payload and re-establish the tenant connection when it runs, or it will silently execute against whichever database happened to be configured last. Needs a `TenantAware` job trait/middleware before any queued feature (notifications, `ProcessOpenAIRequest`, etc.) is tenant-safe.
2. **Local dev DNS** — needs `/etc/hosts` or wildcard dev domain, documented in this repo's README.
3. **Two-connection writes aren't transactional** — accepted as eventual consistency (see Subscription Plan section); needs a retry/reconciliation job, not a fire-and-forget write.
4. **Docker** — `docker-compose.yml` needs no structural change (tenants are additional databases on the same MySQL instance), but confirm before implementation.
5. **Clean Architecture boundary** — decide the namespace split for the new Landlord slice vs existing Tenant-scoped code (e.g. `app/Domain/Landlord/*`, `app/Application/UseCases/Landlord/*` vs the existing unprefixed tenant-scoped structure). Pick this before writing the first migration so it's consistent from the start.
6. **Testing** — `phpunit.xml` currently points at a single SQLite file for tests. Multi-database tests (landlord + tenant) need either two SQLite connections configured for testing, or a strategy to create/drop a throwaway tenant DB per test run. Decide this before writing `TenantProvisioningTest`.

---

## Explicitly out of scope (this phase)

- `lets_jam_app` (Flutter) tenant-awareness.
- Real billing/payment processing on Subscription Plans.
- Custom domains per tenant (beyond `*.app.test` subdomains).
- Multiple GodAdmin roles/permissions.

---

## Roadmap — Sprint 0: Multitenancy Foundation (backend)

### 0.1 Landlord Database & Connection Switching

- [ ] Add `landlord` connection to `config/database.php` (fixed DB, e.g. `starter_kit_landlord`)
- [ ] Add `tenant` connection template to `config/database.php` (database filled at runtime)
- [ ] Create landlord migrations: `godadmins`, `subscription_plans`, `tenants`, `landlord_audit_logs`
- [ ] Create `GodAdmin`, `SubscriptionPlan`, `Tenant`, `LandlordAuditLog` models with explicit `protected $connection = 'landlord'`
- [ ] Add `protected $connection = 'tenant'` (via shared trait) to all existing tenant-scoped models (Admin, User, Chat, Message, AuditLog, File, Setting, Notification, Assistant)
- [ ] Create `IdentifyTenant` middleware — resolves subdomain from `Host` header, looks up active tenant on `landlord`, points `tenant` connection at `tenant.database_name`, purges connection, binds `currentTenant` in container
- [ ] Apply `IdentifyTenant` to all tenant-facing `web`/`api` routes (not to `/god/*`)
- [ ] Document local dev subdomain setup (`/etc/hosts` or wildcard `nip.io` domain) in this repo's README

### 0.2 Tenant Provisioning

- [ ] Add `admins.is_tenant_owner` boolean column (migration, default false)
- [ ] Create `ProvisionTenant` service/artisan command: validates subdomain, `CREATE DATABASE`, inserts `tenants` row, runs tenant migrations, seeds roles/permissions, creates first Admin with `is_tenant_owner = true`, seeds `settings.features.*` from the chosen plan
- [ ] Wire GodAdmin-created provisioning (via Livewire form)
- [ ] Wire self-service provisioning (public sign-up flow, served by Laravel — subdomain + name + plan, no payment)
- [ ] Write feature tests: `TenantProvisioningTest` (both entry points)

### 0.3 GodAdmin (Livewire)

- [ ] Add `godadmin` guard (session driver) in `config/auth.php`, provider backed by `GodAdmin` on `landlord`
- [ ] Routes under `/god/*` (Blade + Livewire, session auth, CSRF)
- [ ] `Login` component
- [ ] `Dashboard` component
- [ ] `SubscriptionPlans/Index` + `SubscriptionPlans/Form` (name, slug, price_cents, features json, limits json, is_active)
- [ ] `Tenants/Index` + `Tenants/Create` + `Tenants/Show` (create tenant manually, view/suspend)
- [ ] Write feature tests: `GodAdminAuthTest`, `SubscriptionPlanManagementTest`, `TenantManagementTest`

### 0.4 Subscription Plan Changes & Branding (Tenant Owner)

- [ ] Create `PATCH /api/admin/tenant/subscription-plan` — `is_tenant_owner`-only, writes `landlord`, re-syncs tenant `settings.features.*`, audit-logs both sides
- [ ] Create `PATCH /api/admin/tenant/branding` — `is_tenant_owner`-only, writes `theme_primary_color`/`theme_secondary_color`/`logo_path` on `landlord`
- [ ] Create `GET /api/tenant/theme` — public, unauthenticated, resolved by `IdentifyTenant`, returns branding for the login page
- [ ] Add retry/reconciliation job for failed cross-connection settings sync
- [ ] Write feature tests: `SubscriptionPlanChangeTest`, `TenantBrandingTest`

### 0.5 Tenant-Aware Queues

- [ ] Add `TenantAware` job trait/middleware — carries `tenant_id` in payload, re-establishes tenant connection when the job runs on a Horizon worker
- [ ] Audit existing queued jobs (notifications, OpenAI processing) and apply the trait
- [ ] Write test verifying a queued job executes against the correct tenant database

---

## Suggested implementation order

1. **0.1** first — nothing else works without connection switching in place.
2. **0.2** next — provisioning is needed to have any tenant DB to test against.
3. **0.5** before or alongside 0.3/0.4 — safer to bake tenant-awareness into queued jobs early rather than retrofit later once more jobs exist.
4. **0.3** and **0.4** can proceed in parallel once 0.1/0.2 are solid.

Update this file's checkboxes as work lands, and update `starter_kit/roadmap.md` + `starter_kit/docs/02-roadmap.md` (the cross-repo coordinator) to keep both in sync.
