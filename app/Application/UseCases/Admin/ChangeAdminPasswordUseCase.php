<?php

namespace App\Application\UseCases\Admin;

use App\Application\UseCases\Template\RenderSystemTemplateUseCase;
use App\Models\Admin;
use App\Notifications\PasswordChangedNotification;
use Illuminate\Support\Facades\Hash;

class ChangeAdminPasswordUseCase
{
    public function __construct(
        private RenderSystemTemplateUseCase $renderSystemTemplate,
    ) {}

    public function execute(string $adminId, string $currentPassword, string $newPassword): array
    {
        $admin = Admin::findOrFail($adminId);

        if (!Hash::check($currentPassword, $admin->password)) {
            throw new \InvalidArgumentException('Current password is incorrect.');
        }

        $admin->update(['password' => Hash::make($newPassword)]);

        $tenant = app()->bound('currentTenant') ? app('currentTenant') : null;
        $rendered = $this->renderSystemTemplate->execute('password_changed_email');

        $admin->notify(new PasswordChangedNotification(
            $rendered['subject'],
            $rendered['body'],
            tenantId: $tenant?->id,
            tenantName: $tenant?->name,
        ));

        return ['success' => true, 'message' => 'Password updated successfully.'];
    }
}
