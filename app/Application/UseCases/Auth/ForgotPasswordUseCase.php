<?php

namespace App\Application\UseCases\Auth;

use App\Models\User;
use App\Notifications\PasswordResetNotification;
use Illuminate\Support\Facades\Password;

class ForgotPasswordUseCase
{
    public function execute(string $email): array
    {
        $status = Password::broker('users')->sendResetLink(
            ['email' => $email],
            function (User $user, string $token) {
                $resetUrl = config('app.frontend_url', 'http://localhost:3000')
                    . '/auth/reset-password?token=' . $token . '&email=' . urlencode($user->email);

                $user->notify(new PasswordResetNotification($resetUrl));
            }
        );

        if ($status === Password::RESET_LINK_SENT) {
            return ['message' => 'Password reset link sent to your email.'];
        }

        throw new \RuntimeException(__($status));
    }
}
