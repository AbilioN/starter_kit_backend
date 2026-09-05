<?php

namespace App\Domain\Services;

use App\Domain\CustomFields\TableSnapshot;

/**
 * Reads what a host table actually looks like.
 *
 * Split from the reconciler so that drift DETECTION is provably read-only:
 * DetectFieldSchemaDriftUseCase depends on this interface and has no way to
 * reach SchemaReconcilerInterface at all, so it cannot repair even by
 * mistake. A repairer that runs on a schedule fixes the symptom every night
 * and nobody ever learns that a restore, a failed job or a bad deploy caused
 * it.
 */
interface SchemaIntrospectorInterface
{
    /** Whether this implementation can run against the current connection. */
    public function assertUsable(): void;

    public function snapshot(string $table): TableSnapshot;

    /**
     * Custom-field columns present on the table that no definition claims.
     *
     * Only `cf_*` names are considered, which is what makes the answer
     * trustworthy: every other column on the table is invisible to this
     * feature by construction.
     *
     * @param  array<int, string>  $claimedColumns
     * @return array<int, string>
     */
    public function unclaimedFieldColumns(string $table, array $claimedColumns): array;
}
