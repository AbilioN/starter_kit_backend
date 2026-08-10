<?php

namespace App\Application\Services;

use App\Models\Admin as AdminModel;

/**
 * Fonte única do corpo de resposta do "meu perfil".
 *
 * GetAdminProfileUseCase e UpdateAdminProfileUseCase montavam o array à mão e
 * tinham divergido: o primeiro devolvia `is_tenant_owner`/`created_at`, o
 * segundo nenhum dos dois mas devolvia `updated_at`. Com quatro chamadores
 * (get, update, upload de avatar, remoção de avatar) cada campo novo teria de
 * ser escrito quatro vezes — foi assim que a divergência nasceu.
 *
 * Reaproveita AdminDto (que já alimenta o login e a lista de admins) e junta
 * só o que é privado do próprio dono: `notification_email` fica deliberadamente
 * fora do DTO, senão vazaria em GET /api/admin/admins para qualquer colega com
 * a permissão `admin-read`.
 */
class AdminProfilePresenter
{
    public static function present(AdminModel $admin): array
    {
        return array_merge(
            $admin->toEntity()->toDto()->toArray(),
            ['notification_email' => $admin->notification_email],
        );
    }

    /** Envelope completo, para os use cases devolverem direto. */
    public static function response(AdminModel $admin): array
    {
        return ['success' => true, 'data' => self::present($admin)];
    }
}
