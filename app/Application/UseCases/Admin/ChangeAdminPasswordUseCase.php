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

    /**
     * `$currentTokenId` é o token de quem está a chamar: todos os OUTROS tokens
     * Sanctum são revogados (trocar a senha deve encerrar as demais sessões),
     * mas o do próprio chamador sobrevive — matá-lo daria 401 no request
     * seguinte, e a UI fica na mesma página depois de gravar.
     */
    public function execute(
        string $adminId,
        string $currentPassword,
        string $newPassword,
        ?string $currentTokenId = null,
    ): array {
        $admin = Admin::findOrFail($adminId);

        if (!Hash::check($currentPassword, $admin->password)) {
            throw new \InvalidArgumentException('Current password is incorrect.');
        }

        $admin->update(['password' => Hash::make($newPassword)]);

        $admin->tokens()
            ->when($currentTokenId !== null, fn ($q) => $q->where('id', '!=', $currentTokenId))
            ->delete();

        $tenant = app()->bound('currentTenant') ? app('currentTenant') : null;
        $rendered = $this->renderSystemTemplate->execute('password_changed_email', preferredLocale: $admin->locale);

        $admin->notify(new PasswordChangedNotification(
            $rendered['subject'],
            $rendered['body'],
            tenantId: $tenant?->id,
            tenantName: $tenant?->name,
        ));

        return ['success' => true, 'message' => 'Password updated successfully.'];
    }
}
