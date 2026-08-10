<?php

namespace App\Application\UseCases\Admin;

use App\Application\Services\AdminProfilePresenter;
use App\Application\UseCases\Audit\LogAuditUseCase;
use App\Models\Admin;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class UpdateAdminAvatarUseCase
{
    /**
     * Prefixo da pasta. Também serve de guarda na remoção do ficheiro antigo:
     * `avatar_path` é uma coluna de texto livre e `Storage::delete()` é
     * destrutivo — limitamos o raio de ação à árvore de avatares.
     */
    private const FOLDER_PREFIX = 'admin-avatars';

    public function __construct(private LogAuditUseCase $logAudit) {}

    public function execute(string $adminId, UploadedFile $file, ?string $tenantId = null): array
    {
        $admin = Admin::findOrFail($adminId);

        // O disco `public` NÃO é tenant-scoped (IdentifyTenant só troca o `s3`),
        // então sem o prefixo por tenant todos partilhariam uma pasta plana.
        $folder = self::FOLDER_PREFIX.'/'.($tenantId ?: 'shared');
        $newPath = Storage::disk('public')->putFile($folder, $file);

        $oldPath = $admin->avatar_path;
        $admin->update(['avatar_path' => $newPath]);

        // Só depois de o banco confirmar: se o update falhar, o ficheiro novo
        // fica órfão (barato) mas o antigo continua a servir a linha existente.
        if ($oldPath && $oldPath !== $newPath && str_starts_with($oldPath, self::FOLDER_PREFIX.'/')) {
            Storage::disk('public')->delete($oldPath);
        }

        $this->logAudit->execute(
            userId: $admin->id,
            userType: 'Admin',
            action: 'avatar_updated',
            modelType: 'Admin',
            modelId: $admin->id,
            oldValues: ['avatar_path' => $oldPath],
            newValues: ['avatar_path' => $newPath],
        );

        return AdminProfilePresenter::response($admin);
    }
}
