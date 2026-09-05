<?php

namespace App\Domain\CustomFields\Hosts;

use App\Domain\CustomFields\CustomFieldHostInterface;
use App\Domain\CustomFields\HostCeilings;
use App\Models\User;

/**
 * The tenant's end users.
 *
 * The second host, and the one where the frozen line is tightest: `email` is
 * the login identity and the target of the verification flow, `password` and
 * `remember_token` are credentials, `email_verified_at` and `last_seen_at`
 * drive real behaviour, and `locale` is the first link of SetLocale's cascade.
 * What a tenant may add here is everything a human reads and nothing the
 * application reasons about — a notice period, an internal reference, a
 * preferred contact time.
 *
 * Its ceilings are deliberately tighter than the agenda's. `users` starts with
 * only one secondary index (the unique on `email`), which sounds like room —
 * but it is the table every login, every token lookup and every chat
 * participant list reads, so an index added here is paid for on the hottest
 * path in the product.
 */
final class UsersHost implements CustomFieldHostInterface
{
    public function key(): string
    {
        return 'users';
    }

    public function table(): string
    {
        return 'users';
    }

    public function modelClass(): string
    {
        return User::class;
    }

    public function writePermission(): string
    {
        return 'user-update';
    }

    public function featureFlag(): string
    {
        return 'features.custom_fields_users';
    }

    public function slots(): array
    {
        return [
            'row.secondary' => 'Under the name, in the user list',
        ];
    }

    public function sections(): array
    {
        return [
            'general' => 'General',
            'contact' => 'Contact',
            'notes' => 'Notes',
        ];
    }

    public function reservedColumns(): array
    {
        return [
            'id',
            // Identity and credentials. Renaming any of these is a release.
            'email', 'password', 'remember_token',
            // Behaviour: verification, the update.last.seen middleware, and
            // the first link of SetLocale's cascade.
            'email_verified_at', 'last_seen_at', 'locale',
            // Read by chat participant lists and every list screen.
            'name',
            'created_at', 'updated_at', 'deleted_at',
        ];
    }

    public function ceilings(): HostCeilings
    {
        // Half the agenda's index budget. Every login and every token lookup
        // reads this table, so an index here is not the same purchase as an
        // index on appointments.
        return new HostCeilings(maxSecondaryIndexes: 24);
    }
}
