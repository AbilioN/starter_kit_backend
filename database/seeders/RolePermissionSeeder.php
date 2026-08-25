<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Links roles to permissions on the CURRENT tenant connection. Runs as part
 * of `tenant:provision`, right after RoleSeeder and PermissionSeeder.
 *
 * Those two created roles and permissions but nothing ever connected them, so
 * a freshly provisioned tenant had roles carrying ZERO permissions. It went
 * unnoticed for as long as it did because the only admin a new tenant has is
 * a super admin, and CheckAdminPermissionUseCase short-circuits on
 * isSuperAdmin() before consulting any role — the bug only appears the moment
 * someone creates a second, ordinary admin, who can then do nothing at all.
 *
 * Separate from AdminRolePermissionSeeder, which does this AND assigns roles
 * to two admins found by hardcoded e-mail addresses (admin@dashboard.com,
 * admin2@dashboard.com). Those exist in the legacy single-tenant fixtures and
 * in no provisioned tenant, and that seeder also calls $this->command->info()
 * unguarded, which is a fatal error when a seeder is invoked as
 * `(new Seeder)->run()` the way provisioning does. This one only does the part
 * that belongs to every tenant.
 *
 * Both roles get every permission, matching what AdminRolePermissionSeeder
 * has always granted. `user` deliberately gets none: it is the role a tenant
 * narrows down or clones when it wants a restricted admin, and starting it
 * with full access would make "restricted" the thing you have to remember to
 * do.
 */
class RolePermissionSeeder extends Seeder
{
    private const FULL_ACCESS_ROLES = ['super-admin', 'admin'];

    public function run(): void
    {
        $permissionIds = Permission::pluck('id')->all();

        if ($permissionIds === []) {
            return;
        }

        foreach (self::FULL_ACCESS_ROLES as $slug) {
            Role::where('slug', $slug)->first()?->permissions()->sync($permissionIds);
        }
    }
}
