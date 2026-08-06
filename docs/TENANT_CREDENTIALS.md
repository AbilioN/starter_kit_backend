# Demo Tenant Owner Credentials

**Local dev only** — these two tenants are provisioned in this environment's `landlord` DB (see `docs/04-local-dev-tenants.md` for how `tenant:provision` created them, and `database/seeders/TenantBrandingSeeder.php` for their default logo/colors). Not present on a fresh clone until both steps are run.

| Tenant | Subdomain | Owner email | Password | `is_tenant_owner` | `is_super_admin` |
|---|---|---|---|---|---|
| Tenant A | `tenant-a` | `admina@tenant-a.test` | `password123` | `true` | `true` |
| Tenant B | `tenant-b` | `adminb@tenant-b.test` | `password123` | `true` | `true` |

Both admins are also `is_super_admin: true` (RBAC bypass) — normal for a tenant's first/owner admin, unrelated to `is_tenant_owner` (which gates the branding/subscription-plan endpoints specifically, see `docs/03-multitenancy-plan.md`).

## Logging in

```
POST /api/admin/login
Body: { "email": "admina@tenant-a.test", "password": "password123" }
```

Needs the tenant resolved on the request — either a real subdomain `Host` header (`tenant-a.starterkit.test`, needs `/etc/hosts`/`nip.io`, see `docs/04-local-dev-tenants.md`) or, for local dev with zero DNS setup, `?tenant=tenant-a` appended to the URL (only accepted when `APP_ENV` is `local`/`testing`):

```bash
curl "http://localhost:8006/api/admin/login?tenant=tenant-a" \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"email":"admina@tenant-a.test","password":"password123"}'
```

## GodAdmin (separate actor, not a tenant admin)

`http://localhost:8006/god/login` — `god@starterkit.test` / `password123`. Session-auth, no relation to the credentials above (see `CLAUDE.md`'s GodAdmin note — no RBAC, privileges are absolute).
