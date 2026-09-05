<?php

namespace App\Domain\CustomFields;

/**
 * The storage a field type asks for, in engine-neutral terms.
 *
 * The planner reasons about these; only MySqlSchemaReconciler turns one into
 * SQL. That split is what lets the planner — where every ceiling rule and the
 * idempotence property live — be unit-tested with no database at all.
 */
final class ColumnSpec
{
    public function __construct(
        /** varchar | text | mediumtext | int | decimal | date | tinyint */
        public readonly string $type,
        public readonly ?int $length = null,
        public readonly ?int $precision = null,
        public readonly ?int $scale = null,
        /**
         * Whether an index may be placed on this column at all. A MEDIUMTEXT
         * cannot take one without a prefix length — MySQL answers error 1170,
         * which SQLite has no equivalent of, so a planner that got this wrong
         * would stay green on the fast gate and fail in production.
         */
        public readonly bool $indexable = false,
    ) {}

    /**
     * The declared byte cost against MySQL's 65,535-byte row limit.
     *
     * utf8mb4 is 4 bytes per character. TEXT-family columns are stored
     * off-page and cost the row only a small pointer, which is the entire
     * reason a display-only field gets TEXT instead of VARCHAR — the study's
     * first pitfall, "the row that got too wide".
     */
    public function declaredBytes(): int
    {
        return match ($this->type) {
            'varchar' => ($this->length ?? 0) * 4 + 2,
            'text', 'mediumtext' => 12,
            'int' => 4,
            'tinyint' => 1,
            'date' => 3,
            'decimal' => (int) ceil((($this->precision ?? 10) + 2) / 2),
            default => 8,
        };
    }

    /** Compares against what information_schema reports, e.g. "varchar(190)". */
    public function columnType(): string
    {
        return match ($this->type) {
            'varchar' => "varchar({$this->length})",
            'decimal' => "decimal({$this->precision},{$this->scale})",
            'tinyint' => 'tinyint(1)',
            default => $this->type,
        };
    }
}
