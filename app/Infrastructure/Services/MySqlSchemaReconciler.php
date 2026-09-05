<?php

namespace App\Infrastructure\Services;

use App\Domain\CustomFields\SchemaIntent;
use App\Domain\Services\SchemaReconcilerInterface;
use Illuminate\Support\Facades\DB;

/**
 * The only place this product issues DDL at runtime.
 *
 * Before this class there was not a single `Schema::` call outside
 * `database/migrations/`; the one raw DDL statement in the whole application
 * was `CREATE DATABASE` during tenant provisioning. So there is no house
 * convention to copy here, and the rules below are the convention.
 */
class MySqlSchemaReconciler implements SchemaReconcilerInterface
{
    /**
     * How long an ALTER will wait for a metadata lock before giving up.
     *
     * `@@lock_wait_timeout` is **31,536,000 seconds** on this server — a full
     * year, MySQL's default. Without this, one ALTER sitting behind a single
     * long-open transaction would queue every subsequent read of the host
     * table behind it, indefinitely. Ten seconds turns a contended table into
     * a failed reconcile a tenant can retry, instead of an outage nobody can
     * attribute.
     */
    private const LOCK_WAIT_SECONDS = 10;

    public function assertUsable(): void
    {
        $driver = DB::connection('tenant')->getDriverName();

        if ($driver !== 'mysql') {
            throw new \RuntimeException(
                "Custom field reconciliation requires MySQL; this connection is [{$driver}]. "
                .'There is deliberately no SQLite implementation: it would be a code path '
                .'production never runs. Feature tests fake this interface instead.'
            );
        }

        $this->assertNotTheSharedTestDatabase();
    }

    public function apply(string $table, array $intents): array
    {
        $this->assertUsable();

        $applied = [];

        DB::connection('tenant')->statement('SET SESSION lock_wait_timeout = '.self::LOCK_WAIT_SECONDS);

        foreach ($intents as $intent) {
            if ($intent->isRefusal()) {
                continue;
            }

            $sql = $this->toSql($table, $intent);

            if ($sql === null) {
                continue;
            }

            DB::connection('tenant')->statement($sql);

            // Appended one at a time, not in a batch at the end. MySQL commits
            // implicitly on DDL, so a process killed halfway leaves real
            // changes behind — and the ledger has to say which ones.
            $applied[] = ['sql' => $sql, 'intent' => $intent->toArray()];
        }

        return $applied;
    }

    /**
     * ALGORITHM and LOCK are always named, never left to MySQL.
     *
     * If MySQL cannot satisfy the named algorithm it errors, the definition
     * becomes `failed` with a readable reason, and nobody's agenda goes down
     * while a table quietly rebuilds. Leaving the choice to the server means
     * an ALTER that is instant on a small table is a COPY on a large one —
     * i.e. the failure only appears on the tenants who can least afford it.
     */
    private function toSql(string $table, SchemaIntent $intent): ?string
    {
        // The prime directive, restated at the last possible moment. The
        // planner already refuses anything that is not cf_<digits>; this is
        // the belt on the buckle, because this method is the one that
        // interpolates a name into SQL.
        $this->assertOwnedColumn($intent->column);

        $t = $this->quote($table);
        $c = $this->quote($intent->column);

        return match ($intent->kind) {
            // INSTANT is genuinely available for ADD COLUMN since 8.0.12, and
            // it is what makes creating a field a sub-second operation on a
            // table with a million rows.
            SchemaIntent::ADD_COLUMN => "ALTER TABLE {$t} ADD COLUMN {$c} "
                .$this->columnDefinition($intent).', ALGORITHM=INSTANT',

            SchemaIntent::ADD_INDEX => "ALTER TABLE {$t} ADD INDEX "
                .$this->quote((string) $intent->indexName)." ({$c}), ALGORITHM=INPLACE, LOCK=NONE",

            SchemaIntent::DROP_INDEX => "ALTER TABLE {$t} DROP INDEX "
                .$this->quote((string) $intent->indexName).', ALGORITHM=INPLACE, LOCK=NONE',

            // INPLACE, not INSTANT. MySQL 8's online-DDL matrix lists renaming
            // a column as Instant=No / In Place=Yes. Naming INSTANT here would
            // make every retire fail, 100% of the time — and because this
            // class refuses to let MySQL downgrade the algorithm, it would
            // fail loudly rather than silently doing something slower.
            SchemaIntent::RETIRE_COLUMN => "ALTER TABLE {$t} RENAME COLUMN {$c} TO "
                .$this->quote($this->assertRetiredName((string) $intent->newName))
                .', ALGORITHM=INPLACE, LOCK=NONE',

            default => null,
        };
    }

    private function columnDefinition(SchemaIntent $intent): string
    {
        $spec = $intent->spec;

        $type = match ($spec->type) {
            'varchar' => "VARCHAR({$spec->length})",
            'text' => 'TEXT',
            'mediumtext' => 'MEDIUMTEXT',
            'int' => 'INT',
            'tinyint' => 'TINYINT(1)',
            'date' => 'DATE',
            'decimal' => "DECIMAL({$spec->precision},{$spec->scale})",
            default => throw new \RuntimeException("Unmapped column spec type [{$spec->type}]."),
        };

        // Always NULL. A new column on a populated table cannot be NOT NULL
        // without a constant default, and "required" is a validation rule
        // rather than a storage constraint: a tenant may make a field required
        // next Tuesday, and last Tuesday's rows have to survive that.
        return $type.' NULL';
    }

    private function assertOwnedColumn(string $column): void
    {
        if (preg_match('/^cf_\d+$/', $column) !== 1) {
            throw new \RuntimeException(
                "Refusing to run DDL against [{$column}]: the reconciler only ever names cf_<n> columns."
            );
        }
    }

    private function assertRetiredName(string $name): string
    {
        if (preg_match('/^cf_\d+_retired_\d{6}$/', $name) !== 1) {
            throw new \RuntimeException("Refusing to rename to [{$name}]: not a retired-column name.");
        }

        return $name;
    }

    /**
     * Backticks, with the identifier already proven to match a strict pattern
     * by the two asserts above. Both belts, because this is the one method in
     * the product that builds DDL from data.
     */
    private function quote(string $identifier): string
    {
        if (preg_match('/^[A-Za-z0-9_]+$/', $identifier) !== 1) {
            throw new \RuntimeException("Refusing to quote identifier [{$identifier}].");
        }

        return '`'.$identifier.'`';
    }

    /**
     * A runtime seatbelt for the test suite.
     *
     * MySQL commits implicitly on DDL, so an ALTER against the shared testing
     * tenant escapes RefreshDatabase's transaction and stays for every later
     * test in the process — with the symptom appearing as a hundred unrelated
     * failures. tests/DdlTestCase.php gives each DDL test its own throwaway
     * database; this refuses to run if one forgot.
     *
     * Compared against `env('TENANT_DB_DATABASE')`, the phpunit default, and
     * NOT against live config: `TenantConnectionSwitcher::run()` and
     * `ProvisionTenantUseCase` both rewrite that config key, so a check
     * against it would have zero discriminating power in either direction.
     */
    private function assertNotTheSharedTestDatabase(): void
    {
        if (! app()->runningUnitTests()) {
            return;
        }

        $shared = env('TENANT_DB_DATABASE');
        $current = DB::connection('tenant')->getDatabaseName();

        if ($shared !== null && $shared !== '' && $current === $shared) {
            throw new \RuntimeException(
                "Refusing to run DDL against the shared test tenant database [{$current}]. "
                .'Extend tests/DdlTestCase.php, which provisions a throwaway database per test.'
            );
        }
    }
}
