<?php

namespace App\Domain\AgentTools;

/**
 * A handler's return value, with truncation as part of the type.
 *
 * Handlers cannot return a bare array on purpose. Silent truncation is worse
 * than an error — it makes the model reason confidently over partial data — so
 * "did this get cut" is structural here rather than a convention someone has to
 * remember.
 */
final readonly class AgentToolResult
{
    private function __construct(
        public mixed $value,
        public int $rowCount,
        public bool $truncated,
    ) {}

    /** A single value: a count, a metrics object, one record. */
    public static function scalar(mixed $value): self
    {
        return new self($value, 1, false);
    }

    /**
     * A list, capped at $maxRows. The cap is applied here rather than left to
     * each handler's query, so a handler that forgets a LIMIT still cannot
     * return ten thousand rows.
     */
    public static function rows(array $rows, int $maxRows): self
    {
        $rows = array_values($rows);
        $truncated = count($rows) > $maxRows;

        return new self(
            $truncated ? array_slice($rows, 0, $maxRows) : $rows,
            $truncated ? $maxRows : count($rows),
            $truncated,
        );
    }

    /** Used by the executor when the byte backstop trims an already-capped list. */
    public function truncatedTo(mixed $value, int $rowCount): self
    {
        return new self($value, $rowCount, true);
    }
}
