<?php

namespace App\Application\UseCases\Auth;

use App\Application\UseCases\Template\RenderSystemTemplateUseCase;
use App\Models\User;
use App\Notifications\PasswordResetNotification;
use Illuminate\Support\Facades\Password;

class ForgotPasswordUseCase
{
    public function __construct(
        private RenderSystemTemplateUseCase $renderSystemTemplate,
    ) {}

    public function execute(string $email): array
    {
        // Rendered here, not inside toMail() - the notification is queued,
        // and neither app('currentTenant') nor the tenant's own template are
        // reachable from a worker with no HTTP request behind it.
        $tenant = app()->bound('currentTenant') ? app('currentTenant') : null;

        $status = Password::broker('users')->sendResetLink(
            ['email' => $email],
            function (User $user, string $token) use ($tenant) {
                $resetUrl = config('app.frontend_url', 'http://localhost:3000')
                    . '/auth/reset-password?token=' . $token . '&email=' . urlencode($user->email);

                $rendered = $this->renderSystemTemplate->execute('password_reset_email', [
                    'reset_url' => $resetUrl,
                ]);

                $user->notify(new PasswordResetNotification(
                    $rendered['subject'],
                    $rendered['body'],
                    tenantId: $tenant?->id,
                    tenantName: $tenant?->name,
                ));
            }
        );

        if ($status === Password::RESET_LINK_SENT) {
            return ['message' => 'Password reset link sent to your email.'];
        }

        throw new \RuntimeException(__($status));
    }
}
