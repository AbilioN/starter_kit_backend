<?php

namespace App\Application\UseCases\Admin;

use App\Application\Services\AdminProfilePresenter;
use App\Application\UseCases\Audit\LogAuditUseCase;
use App\Models\Admin;

class UpdateAdminProfileUseCase
{
    public function __construct(private LogAuditUseCase $logAudit) {}

    /**
     * `$updateNotificationEmail` existe para separar "campo não enviado" de
     * "enviado como null" (= limpar o endereço). Um `?string` sozinho colapsaria
     * os dois casos e tornaria impossível apagar o endereço.
     *
     * A autorização do campo é feita antes daqui, em
     * UpdateAdminProfileRequest::authorize() — só o tenant owner pode tocá-lo.
     */
    public function execute(
        string $adminId,
        ?string $name = null,
        bool $updateNotificationEmail = false,
        ?string $notificationEmail = null,
    ): array {
        $admin = Admin::findOrFail($adminId);

        $changes = [];
        if ($name !== null) {
            $changes['name'] = $name;
        }
        if ($updateNotificationEmail) {
            $changes['notification_email'] = $notificationEmail;
        }

        if ($changes !== []) {
            $previousNotificationEmail = $admin->notification_email;
            $admin->update($changes);

            // Auditar a mudança do endereço crítico especificamente: como ele não
            // passa por verificação nesta versão, o rasto de quem o alterou e para
            // onde é a principal mitigação.
            if ($updateNotificationEmail && $previousNotificationEmail !== $notificationEmail) {
                $this->logAudit->execute(
                    userId: $admin->id,
                    userType: 'Admin',
                    action: 'notification_email_updated',
                    modelType: 'Admin',
                    modelId: $admin->id,
                    oldValues: ['notification_email' => $previousNotificationEmail],
                    newValues: ['notification_email' => $notificationEmail],
                );
            }
        }

        return AdminProfilePresenter::response($admin);
    }
}
