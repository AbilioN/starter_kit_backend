# Running the Tests

**Created:** 2026-08-21

There are two ways to run the suite, and the difference between them is not a
detail.

---

## The fast run (SQLite)

```bash
docker compose exec app php artisan test
```

This is what the `sqlite` CI job runs on every push. It is the everyday gate:
fast, and it answers "does the logic still work".

## The real-engine run (MySQL)

```bash
vendor/bin/phpunit -c phpunit.mysql.xml
```

Same tests, same `tests/` directory — only the database engine changes.

### Why it exists

**SQLite is loosely typed and accepts writes MySQL rejects.** On 2026-08-21 that
difference was found to have hidden a complete outage: `notifications.notifiable_id`
was created as a `bigint` while every notifiable (Admin, User) has a UUID primary
key. MySQL rejected every insert, Laravel swallowed the exception, and the
`notifications` table was empty in **every** tenant — the bell, `/notifications`
and every `database`-channel notification in the product had never worked. The
SQLite suite was green the whole time, and a `Notification::fake()` test passed
while nothing was ever delivered.

Column types, MySQL strict mode, foreign keys, index length limits and collation
only exist in this run.

### ⚠️ It needs its own MySQL server

Tenant provisioning tests **create and drop whole databases** named after the
tenant's subdomain (`starter_kit_tenant_<subdomain>`). Some fixtures use
subdomains like `tenant-a`, which is also a real local development tenant.
Pointing this run at a MySQL server that holds real data risks dropping it.

In CI this is safe by construction: the `mysql` job gets a disposable service
container. Locally, use a throwaway MySQL, or accept the risk knowingly.

### Running it locally against the docker stack

```bash
# One-time: the landlord and tenant connections need their own databases
docker compose exec -T db sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" -e "
  CREATE DATABASE IF NOT EXISTS starter_kit_testing;
  CREATE DATABASE IF NOT EXISTS starter_kit_testing_landlord;
  CREATE DATABASE IF NOT EXISTS starter_kit_testing_tenant;
  GRANT ALL ON *.* TO starter_kit_backend@\"%\"; FLUSH PRIVILEGES;"'

docker compose exec -T \
  -e DB_HOST=db \
  -e LANDLORD_DB_DATABASE=starter_kit_testing_landlord \
  -e TENANT_DB_DATABASE=starter_kit_testing_tenant \
  app vendor/bin/phpunit -c phpunit.mysql.xml
```

PHPUnit does not overwrite an environment variable that already exists, so the
values in `phpunit.mysql.xml` are defaults (aimed at CI) and any of them can be
overridden from the shell, as above.

Use `vendor/bin/phpunit`, not `php artisan test` — the artisan wrapper does not
forward `-c` to PHPUnit, so it silently runs the SQLite config instead.

### The `mysql-ddl` group

```bash
docker compose exec -T \
  -e DB_HOST=db \
  -e LANDLORD_DB_DATABASE=starter_kit_testing_landlord \
  -e TENANT_DB_DATABASE=starter_kit_testing_tenant \
  app vendor/bin/phpunit -c phpunit.mysql.xml --group=mysql-ddl
```

Tests that run **real DDL** — `ALTER TABLE`, `CREATE INDEX` — added with the
tenant-defined-fields work. They extend `tests/DdlTestCase.php` and use
`tests/Concerns/RequiresMySql.php`, so they self-skip under the fast run rather
than testing a SQLite code path production never executes.

They are in the ordinary MySQL run too; the group exists so they can be run
alone, because each one **provisions and drops its own database** and they are
correspondingly slow (~25s each).

**Why they need their own database.** The teardown above fires only when a new
database appeared during the test. A test that merely `ALTER`s a table creates
none, so the cleanup is skipped — while MySQL has already implicitly committed
the enclosing transaction. The column would stay for every later test in the
process, and the symptom would be a hundred unrelated failures downstream.
`DdlTestCase` sidesteps that by giving each test a throwaway database created
*after* the "before" list is captured, so the existing net catches it.

Two rules ride in that class's docblock and are not optional: **nothing may be
seeded into the shared tenant before the connection switch** (repointing purges
the connection the open transaction lives on), and **assertions read from
`information_schema`**, not from the transaction — the DDL has committed, so a
rolled-back transaction proves nothing about what actually happened.

`tests/Feature/System/DdlHarnessTest.php` proves the containment itself: it
alters a table, asserts the change landed in its own database, and asserts the
shared `starter_kit_testing_tenant` never saw it.

---

## Why the harness has engine-specific code

Two things in `tests/TenantTestCase.php` exist only because of this second run:

- **`connectionsToTransact()` is computed, not a fixed array.** The default
  connection is named `sqlite` in one config and `mysql` in the other. Hardcoding
  the name made every MySQL test try to open a *file* named after the database.
- **A teardown that truncates and drops, on MySQL only.** RefreshDatabase relies
  on wrapping each test in a transaction. SQLite makes DDL transactional, so a
  test that provisions a tenant (`CREATE DATABASE` + migrations) rolls back like
  anything else. **MySQL implicitly commits on DDL**: the moment a provisioning
  test runs, the enclosing transaction is gone and everything written so far is
  permanent. The symptom is not that test failing — it is the next hundred tests
  failing on duplicate keys for rows they never inserted.

Neither is a workaround for a bug in the application; both are the price of
running the same tests on two engines, and that price is worth paying.
