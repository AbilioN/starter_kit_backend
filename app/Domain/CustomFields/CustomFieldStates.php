<?php

namespace App\Domain\CustomFields;

/**
 * A definition's lifecycle, in the domain layer so the pure planner can reason
 * about it without reaching for an Eloquent model.
 *
 * An explicit state and NOT soft deletes. A trashed row is invisible to
 * max(num) under the global scope while unique(host, num) still sees it, so
 * the first field created after any deletion would collide — forever, with a
 * 500 and no obvious cause. The reconciler also needs to see retired rows to
 * know those columns are its business rather than drift.
 */
final class CustomFieldStates
{
    /** Written; the column does not exist yet. */
    public const PENDING = 'pending';

    /** The column exists and matches the definition. The only readable state. */
    public const LIVE = 'live';

    /** Rename-away and index-drop planned or in flight. */
    public const RETIRING = 'retiring';

    /** Column parked as cf_N_retired_YYMMDD. The data is still there. */
    public const RETIRED = 'retired';

    /** Column actually dropped, by an operator, deliberately. */
    public const PURGED = 'purged';

    /** The reconcile refused. state_error_code says why, translatably. */
    public const FAILED = 'failed';

    /**
     * The column vanished under us — a restore of a dump predating the field,
     * or someone with a mysql prompt.
     *
     * The sweep may demote live -> missing, and that is the one schema-shaped
     * thing detection is allowed to write. Repairing the COLUMN still needs a
     * human to type the command; demoting the row is what stops the accessor
     * naming a column that is not there.
     */
    public const MISSING = 'missing';

    /** @return array<int, string> */
    public static function readable(): array
    {
        return [self::LIVE];
    }

    /** @return array<int, string> states that own, or are about to own, a column */
    public static function reconcilable(): array
    {
        return [self::PENDING, self::LIVE, self::FAILED, self::MISSING, self::RETIRING];
    }

    /**
     * @return array<int, string> states that spend the tenant's paid quota.
     *   Not every row: definitions are never deleted, so counting all of them
     *   would let a field retired last year permanently occupy a slot the
     *   tenant is still paying for.
     */
    public static function countsTowardPlanLimit(): array
    {
        return [self::PENDING, self::LIVE, self::FAILED];
    }
}
