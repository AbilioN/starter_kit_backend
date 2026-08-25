<?php

namespace App\Application\UseCases\Auth;

use App\Application\UseCases\Template\RenderSystemTemplateUseCase;
use App\Models\User;
use App\Notifications\PasswordChangedNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

class ResetPasswordUseCase
{
    public function __construct(
        private RenderSystemTemplateUseCase $renderSystemTemplate,
    ) {}

    public function execute(string $token, string $email, string $password): array
    {
        $tenant = app()->bound('currentTenant') ? app('currentTenant') : null;

        $status = Password::broker('users')->reset(
            [
                'email'                 => $email,
                'password'              => $password,
                'password_confirmation' => $password,
                'token'                 => $token,
            ],
            function (User $user, string $password) use ($tenant) {
                $user->forceFill(['password' => Hash::make($password)])->save();

                $rendered = $this->renderSystemTemplate->execute('password_changed_email', preferredLocale: $user->locale);

                $user->notify(new PasswordChangedNotification(
                    $rendered['subject'],
                    $rendered['body'],
                    tenantId: $tenant?->id,
                    tenantName: $tenant?->name,
                ));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return ['message' => 'Password has been reset successfully.'];
        }

        throw new \RuntimeException(__($status));
    }
}
