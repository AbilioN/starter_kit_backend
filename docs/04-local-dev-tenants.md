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

## Option C — `?tenant=` query param (no DNS at all, local/testing only)

For clients that can't easily point at a custom hostname (a frontend dev server on plain `localhost`, a quick `curl`), `IdentifyTenant` falls back to a `?tenant=<subdomain>` query param when the request's Host header doesn't resolve to a tenant:

```
http://localhost:8006/api/admin/login?tenant=tenant-a
```

This fallback is gated to `app()->environment(['local', 'testing'])` — it never activates outside those, since letting a query param override subdomain-based tenant resolution in production would undermine the whole point of subdomain isolation. It's genuinely a dev convenience, not part of the tenant-resolution contract.

## Provisioning a tenant to test against

Use the `tenant:provision` artisan command (see `app/Console/Commands/ProvisionTenantCommand.php`) — it creates the database, migrates it, seeds roles/permissions, and creates the first (tenant-owner) admin in one step:

```bash
docker compose exec app php artisan tenant:provision "Tenant A" tenant-a \
    --admin-email=owner@tenant-a.test --admin-password=password123
```

Then log in as that admin against the resolved subdomain: `POST http://tenant-a.starterkit.test:8006/api/admin/login`.

### One-time MySQL grant

The app's DB user (`DB_USERNAME`, default `starter_kit_backend`) only has privileges on its own single database out of the box — `CREATE DATABASE` for a new tenant will fail with `Access denied` until it's granted broader privileges. `docker-setup.sh` does this automatically on a fresh setup; if you're working against an existing container, run it manually once:

```bash
docker compose exec db mysql -uroot -ppassword \
    -e "GRANT ALL PRIVILEGES ON *.* TO 'starter_kit_backend'@'%'; FLUSH PRIVILEGES;"
```

## GodAdmin

`docker-setup.sh` seeds a default GodAdmin (`god@starterkit.test` / `password123`) after migrating the landlord DB. To (re)seed it manually:

```bash
docker compose exec app php artisan db:seed --class=GodAdminSeeder --force
```

Log in at `http://localhost:8006/god/login` — GodAdmin routes are never subdomain-resolved (they live outside `tenant.identify`).
