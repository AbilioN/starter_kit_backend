<?php

namespace App\Application\UseCases\User;

use App\Models\User;
use App\Notifications\PasswordChangedNotification;
use Illuminate\Support\Facades\Hash;

class ChangeUserPasswordUseCase
{
    public function execute(string $userId, string $currentPassword, string $newPassword): array
    {
        $user = User::findOrFail($userId);

        if (!Hash::check($currentPassword, $user->password)) {
            throw new \InvalidArgumentException('Current password is incorrect.');
        }

        $user->update(['password' => Hash::make($newPassword)]);
        $user->notify(new PasswordChangedNotification());

        return ['success' => true, 'message' => 'Password updated successfully.'];
    }
}
