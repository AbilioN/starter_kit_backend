<?php

namespace App\Application\UseCases\Admin;

use App\Models\Admin;
use App\Notifications\PasswordChangedNotification;
use Illuminate\Support\Facades\Hash;

class ChangeAdminPasswordUseCase
{
    public function execute(string $adminId, string $currentPassword, string $newPassword): array
    {
        $admin = Admin::findOrFail($adminId);

        if (!Hash::check($currentPassword, $admin->password)) {
            throw new \InvalidArgumentException('Current password is incorrect.');
        }

        $admin->update(['password' => Hash::make($newPassword)]);
        $admin->notify(new PasswordChangedNotification());

        return ['success' => true, 'message' => 'Password updated successfully.'];
    }
}
