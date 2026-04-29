# Starter Kit BtoB — Master Plan
**Created:** 2026-04-29 18:42  
**Status:** In progress  
**Purpose:** Canonical reference for build order. Each completed item links to its own timestamped doc.

---

## Context for Future Agents

This is a **Laravel 11 / PHP 8.2** BtoB backend starter kit with Clean Architecture + DDD layering.

- Admin client: **Nuxt** (separate repo)
- User client: **Flutter** (separate repo)
- After implementing each feature, a new timestamped `.md` is created in `/docs` with full technical context for downstream agents.
- The end goal is to **fork this repo** into a school management system once the starter kit is complete.

### Architecture Quick Reference
```
Presentation  → app/Http/Controllers, Requests, Middleware, routes/
Application   → app/Application/UseCases, DTOs, Services (Eloquent→Domain bridges)
Domain        → app/Domain/Entities, Repositories (interfaces), Services (interfaces)
Infrastructure→ app/Infrastructure/Repositories, Services (Eloquent implementations)
Models        → app/Models (Eloquent only)
```

**Dependency rule:** Controllers → UseCases → Domain interfaces ← Infrastructure implementations.  
Repositories return **domain entities**, never DTOs.

---

## What Is Already Built

| Module | Status | Notes |
|---|---|---|
| Auth (users + admins) | ✅ Done | Sanctum, email verify, multi-guard |
| RBAC | ✅ Done | Roles, Permissions, AdminRole, AdminRolePermission |
| Chat | ✅ Done | Real-time via Pusher, messages, participants |
| Audit logs | ✅ Done | Immutable, auto-logging trait, `audit-read` permission |
| Docker setup | ✅ Done | MySQL runtime, SQLite tests |
| Tests | ✅ Done | Feature + Unit, PHPUnit |

---

## Build Tier List

### Tier 1 — Core Infrastructure (do first, everything depends on these)

| # | Feature | Why | Status |
|---|---|---|---|
| 1 | **Settings System** | Foundation for feature flags, white-label, module toggles | ✅ Done |
| 2 | **Horizon + Redis Queues** | Production-ready queue visibility; needed by email, AI, chat jobs | ✅ Done |
| 3 | **Notification System** | Email + in-app + push; every feature will emit notifications | ✅ Done |
| 4 | **File Storage** | S3-compatible, chunked upload; needed by AI, PDF, school fork | ✅ Done |

### Tier 2 — High-Value Features

| # | Feature | Why | Status |
|---|---|---|---|
| 5 | **AI Agent Integration** | Streaming, tool use, session context — core differentiator | ⏳ Pending |
| 6 | **PDF Export** | dompdf; reports, invoices, certificates | ⏳ Pending |
| 7 | **2FA (TOTP)** | BtoB clients demand it for admin accounts | ⏳ Pending |
| 8 | **Outbound Webhooks** | Third-party integrations, event delivery | ⏳ Pending |
| 9 | **Soft Deletes** | LGPD/GDPR compliance, data recovery | ⏳ Pending |

### Tier 3 — Modular Architecture

| # | Feature | Why | Status |
|---|---|---|---|
| 10 | **Module System** | `config/modules.php` + conditional Service Providers; extract Chat as reference | ⏳ Pending |
| 11 | **Install Command** | `php artisan starter-kit:install --with-chat` for new projects | ⏳ Pending |

### Tier 4 — Post-Fork (school management)

| # | Feature | Why | Status |
|---|---|---|---|
| 12 | **Multi-tenancy** | Changes DB schema assumptions — defer until fork | ⏳ Deferred |
| 13 | **Billing / Stripe** | Domain-specific, do in the fork | ⏳ Deferred |
| 14 | **Reporting Dashboard** | Domain-specific metrics | ⏳ Deferred |

---

## Doc Index (timestamped, in build order)

| Timestamp | File | Feature |
|---|---|---|
| 2026-04-29_00-00 | `2026-04-29_00-00_FOUNDATION.md` | Everything built before Tier 1 (auth, RBAC, chat, audit) |
| 2026-04-29_18-42 | `2026-04-29_18-42_STARTER_KIT_MASTER_PLAN.md` | This file — master plan and tier list |
| 2026-04-29_19-00 | `2026-04-29_19-00_TIER1_IMPLEMENTATION.md` | Tier 1 — Settings, Horizon, Notifications, File Storage |

> **Convention:** After finishing each feature, create `YYYY-MM-DD_HH-MM_FEATURE_NAME.md` in `/docs` and add a row to the table above.

---

## Decisions & Non-Goals

- **No multi-tenancy in starter kit** — adds DB schema complexity for zero benefit before the fork.
- **Chat is the reference module** for the future module extraction (Tier 3).
- **Admin is Nuxt, User is Flutter** — this repo is API-only; no SSR or frontend assets.
- **Each .md doc is written for a fresh agent** — assume zero prior context, include file paths and conventions.
