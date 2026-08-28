<?php

namespace App\Application\UseCases\User;

use App\Models\User;

/**
 * Counts users in the current tenant, optionally within a signup window.
 *
 * Deliberately takes no actor and reads no session. It is called both from
 * ordinary request context and from the agent tool executor, where there is no
 * authenticated user at all — the actor arrives in the grant. A use case that
 * quietly depends on `Auth::` works in one and returns nothing in the other,
 * without erroring (see docs/12 §3).
 */
class CountUsersUseCase
{
    public function execute(?string $createdAfter = null, ?string $createdBefore = null): int
    {
        return User::query()
            ->when($createdAfter, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($createdBefore, fn ($query, $date) => $query->whereDate('created_at', '<=', $date))
            ->count();
    }
}
