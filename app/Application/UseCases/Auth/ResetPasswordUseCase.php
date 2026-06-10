<?php

namespace App\Application\UseCases\Auth;

use App\Models\User;
use App\Notifications\PasswordChangedNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

class ResetPasswordUseCase
{
    public function execute(string $token, string $email, string $password): array
    {
        $status = Password::broker('users')->reset(
            [
                'email'                 => $email,
                'password'              => $password,
                'password_confirmation' => $password,
                'token'                 => $token,
            ],
            function (User $user, string $password) {
                $user->forceFill(['password' => Hash::make($password)])->save();
                $user->notify(new PasswordChangedNotification());
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return ['message' => 'Password has been reset successfully.'];
        }

        throw new \RuntimeException(__($status));
    }
}
