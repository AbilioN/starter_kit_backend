<?php

namespace App\Application\CustomFields;

use App\Models\Admin;

/**
 * Builds a FieldViewer from the authenticated admin, once per request.
 *
 * Memoised on the instance, and the instance is resolved per request from the
 * controller — never registered as a container singleton. On a long-lived
 * Horizon worker a singleton would carry tenant A's admin into tenant B's
 * job, which is the SettingRepository cache bug in a different costume.
 */
class FieldViewerFactory
{
    /** @var array<string, FieldViewer> */
    private array $memo = [];

    public function forAdmin(?Admin $admin): FieldViewer
    {
        if ($admin === null) {
            return FieldViewer::system();
        }

        return $this->memo[$admin->id] ??= new FieldViewer(
            adminId: $admin->id,
            roleIds: $this->roleIds($admin),
            bypass: (bool) $admin->is_super_admin,
        );
    }

    /**
     * ALL assigned roles, regardless of `is_active`.
     *
     * CheckAdminPermissionUseCase filters to active roles, which is correct
     * there: for GRANTS, dropping an inactive role is fail-closed. Here the
     * rules are DENIALS, so the same filter would be fail-OPEN — deactivating
     * the `support` role would remove its `hidden` rule from resolution while
     * the admin still holds the role, and the field would become visible to
     * exactly the people it was hidden from.
     *
     * @return array<int, string>
     */
    private function roleIds(Admin $admin): array
    {
        return $admin->roles()->pluck('roles.id')->map(fn ($id) => (string) $id)->values()->all();
    }
}
