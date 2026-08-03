# Local Dev: Tenant Subdomains

Every tenant-facing route (`routes/api.php`, wrapped in `tenant.identify`) resolves the tenant from the request's subdomain. Locally, that means each tenant needs a resolvable hostname.

## Option A — `/etc/hosts` entries (per tenant)

Add one line per tenant you're testing against:

```
127.0.0.1 tenant-a.starterkit.test
127.0.0.1 tenant-b.starterkit.test
```

Then hit the app via `http://tenant-a.starterkit.test:8006/api/...` — the `:8006` matches `docker-compose.yml`'s nginx port mapping.

## Option B — wildcard `nip.io` domain (zero config)

`nip.io` resolves any `*.127.0.0.1.nip.io` hostname to `127.0.0.1` with no `/etc/hosts` edits:

```
http://tenant-a.127.0.0.1.nip.io:8006/api/...
```

Useful for quickly testing a new tenant without editing `/etc/hosts` each time.

## Provisioning a tenant to test against

Tenants aren't seeded automatically — see `docs/03-multitenancy-plan.md` for the provisioning flow (Sprint 0.2). Until that lands, create one directly:

```bash
docker compose exec app php artisan tinker
>>> $tenant = \App\Models\Tenant::create([
...     'name' => 'Tenant A',
...     'subdomain' => 'tenant-a',
...     'database_name' => 'starter_kit_tenant_a',
...     'status' => 'active',
...     'created_via' => 'godadmin',
... ]);
```

Note `database_name` must point at a MySQL database that actually exists (`CREATE DATABASE starter_kit_tenant_a;`) with the tenant schema migrated (`php artisan migrate --database=tenant --path=database/migrations/tenant`, after pointing the `tenant` connection at it — see `IdentifyTenant`'s connection-switching logic for the exact mechanism).
