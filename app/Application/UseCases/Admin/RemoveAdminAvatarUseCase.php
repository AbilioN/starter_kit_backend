<?php

namespace App\Application\UseCases\Admin;

use App\Application\Services\AdminProfilePresenter;
use App\Application\UseCases\Audit\LogAuditUseCase;
use App\Models\Admin;
use Illuminate\Support\Facades\Storage;

class RemoveAdminAvatarUseCase
{
    private const FOLDER_PREFIX = 'admin-avatars';

    public function __construct(private LogAuditUseCase $logAudit) {}

    /** Idempotente: sem avatar, devolve 200 com avatar_url null em vez de erro. */
    public function execute(string $adminId): array
    {
        $admin = Admin::findOrFail($adminId);
        $oldPath = $admin->avatar_path;

        if ($oldPath === null) {
            return AdminProfilePresenter::response($admin);
        }

        $admin->update(['avatar_path' => null]);

        if (str_starts_with($oldPath, self::FOLDER_PREFIX.'/')) {
            Storage::disk('public')->delete($oldPath);
        }

        $this->logAudit->execute(
            userId: $admin->id,
            userType: 'Admin',
            action: 'avatar_removed',
            modelType: 'Admin',
            modelId: $admin->id,
            oldValues: ['avatar_path' => $oldPath],
            newValues: ['avatar_path' => null],
        );

        return AdminProfilePresenter::response($admin);
    }
}
