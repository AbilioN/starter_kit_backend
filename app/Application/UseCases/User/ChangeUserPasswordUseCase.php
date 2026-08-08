<?php

namespace App\Application\UseCases\User;

use App\Application\UseCases\Template\RenderSystemTemplateUseCase;
use App\Models\User;
use App\Notifications\PasswordChangedNotification;
use Illuminate\Support\Facades\Hash;

class ChangeUserPasswordUseCase
{
    public function __construct(
        private RenderSystemTemplateUseCase $renderSystemTemplate,
    ) {}

    public function execute(string $userId, string $currentPassword, string $newPassword): array
    {
        $user = User::findOrFail($userId);

        if (!Hash::check($currentPassword, $user->password)) {
            throw new \InvalidArgumentException('Current password is incorrect.');
        }

        $user->update(['password' => Hash::make($newPassword)]);

        $tenant = app()->bound('currentTenant') ? app('currentTenant') : null;
        $rendered = $this->renderSystemTemplate->execute('password_changed_email');

        $user->notify(new PasswordChangedNotification(
            $rendered['subject'],
            $rendered['body'],
            tenantId: $tenant?->id,
            tenantName: $tenant?->name,
        ));

        return ['success' => true, 'message' => 'Password updated successfully.'];
    }
}
