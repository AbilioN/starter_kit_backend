<?php

namespace App\Application\UseCases\Admin;

use App\Domain\Exceptions\UserNotFoundException;
use App\Models\User;

/**
 * Soft delete — User has used SoftDeletes since Sprint 1.5.
 *
 * The row stays, which is what the audit log and any historical appointment
 * pointing at this person need, and it is why the assertion in a test must be
 * assertSoftDeleted rather than assertDatabaseMissing.
 */
class DeleteUserUseCase
{
    public function execute(string $userId): void
    {
        $user = User::find($userId);

        if (! $user) {
            throw new UserNotFoundException("User with ID {$userId} not found");
        }

        $user->delete();
    }
}
