<?php

namespace Database\Seeders;

use App\Domain\Services\TenantConnectionSwitcherInterface;
use App\Models\Admin;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * Landlord seeder that writes into each demo tenant's own database. Invoke
 * directly:
 * php artisan db:seed --class=DemoAdminSeeder
 *
 * Gives every demo tenant (tenant-a, tenant-b, tenant-c) three admins on three
 * different roles, so the panel can be demonstrated with more than one actor:
 * a chat needs two people to be a conversation, and RBAC only means something
 * when someone in the room is missing a permission.
 *
 *   owner    super-admin   everything (the admin tenant:provision created)
 *   manager  admin         everything, but through roles rather than a bypass
 *   support  support       chat + read-only visibility - created here
 *
 * ## Two things this fixes that provisioning leaves undone
 *
 * 1. `tenant:provision` runs RoleSeeder and PermissionSeeder but never links
 *    them (AdminRolePermissionSeeder is not part of that chain), so a freshly
 *    provisioned tenant has roles carrying ZERO permissions. It goes unnoticed
 *    because the only admin that exists is a super admin, and
 *    CheckAdminPermissionUseCase short-circuits on isSuperAdmin() before any
 *    role is consulted. The first non-super admin created in such a tenant can
 *    do nothing at all. This seeder links them.
 *
 * 2. The owner has no role row either - it is a super admin by column, not by
 *    role. Left that way the panel shows the tenant's own owner with a blank
 *    role, so this assigns super-admin to it as well. The column keeps being
 *    what actually grants access; the role is what makes the screen honest.
 *
 * The support role is the interesting one for a demo: chat-read + chat-manage
 * mean it can use the chat and talk to the AI agents, while the absence of
 * admin-* and audit-read means the same screens the owner sees come back 403.
 * That contrast is the point - three admins with identical powers demonstrate
 * nothing.
 *
 * Idempotent: admins are matched by email and roles by slug, so re-running
 * re-syncs permissions without duplicating anyone. Passwords are reset to the
 * shared demo password on every run, deliberately - a demo environment where
 * nobody remembers the password is worse than one with a well-known one.
 */
class DemoAdminSeeder extends Seeder
{
    private const PASSWORD = 'password123';

    private const TENANTS = ['tenant-a', 'tenant-b', 'tenant-c'];

    /**
     * Everything the support role may do. Deliberately narrow: it is the
     * evidence that RBAC is switched on.
     */
    private const SUPPORT_PERMISSIONS = [
        'chat-read',
        'chat-manage',
        'user-read',
        'file-read',
    ];

    public function __construct(
        private readonly TenantConnectionSwitcherInterface $tenantConnection,
    ) {}

    public function run(): void
    {
        foreach (self::TENANTS as $subdomain) {
            $tenant = Tenant::where('subdomain', $subdomain)->first();

            if (! $tenant) {
                $this->command?->warn("Skipping '{$subdomain}': tenant not provisioned yet.");

                continue;
            }

            $this->tenantConnection->run(
                $tenant->database_name,
                fn () => $this->seedTenant($subdomain),
            );
        }
    }

    private function seedTenant(string $subdomain): void
    {
        $letter = substr($subdomain, -1);
        $domain = "{$subdomain}.test";

        $superAdminRole = $this->syncRole('super-admin', 'Super Admin', Permission::pluck('id')->all());
        $adminRole = $this->syncRole('admin', 'Administrator', Permission::pluck('id')->all());
        $supportRole = $this->syncRole(
            'support',
            'Support',
            Permission::whereIn('slug', self::SUPPORT_PERMISSIONS)->pluck('id')->all(),
            'Chat and read-only access - no admin management, no audit log.',
        );

        // The owner already exists (tenant:provision created it); matching on
        // its email updates rather than duplicating.
        $owner = $this->syncAdmin(
            email: "admin{$letter}@{$domain}",
            name: ucfirst($subdomain).' Owner',
            isSuperAdmin: true,
            isTenantOwner: true,
        );

        $manager = $this->syncAdmin(
            email: "manager@{$domain}",
            name: ucfirst($subdomain).' Manager',
            isSuperAdmin: false,
            isTenantOwner: false,
        );

        $support = $this->syncAdmin(
            email: "support@{$domain}",
            name: ucfirst($subdomain).' Support',
            isSuperAdmin: false,
            isTenantOwner: false,
        );

        $this->assignRole($owner, $superAdminRole, $owner);
        $this->assignRole($manager, $adminRole, $owner);
        $this->assignRole($support, $supportRole, $owner);

        $this->command?->info("[{$subdomain}] owner, manager and support ready (super-admin / admin / support).");
    }

    /**
     * @param  array<int, string>  $permissionIds
     */
    private function syncRole(string $slug, string $name, array $permissionIds, ?string $description = null): Role
    {
        $role = Role::firstOrCreate(
            ['slug' => $slug],
            ['name' => $name, 'description' => $description ?? $name, 'is_active' => true],
        );

        $role->permissions()->sync($permissionIds);

        return $role;
    }

    private function syncAdmin(string $email, string $name, bool $isSuperAdmin, bool $isTenantOwner): Admin
    {
        return Admin::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => self::PASSWORD,
                'is_active' => true,
                'is_super_admin' => $isSuperAdmin,
                'is_tenant_owner' => $isTenantOwner,
            ],
        );
    }

    private function assignRole(Admin $admin, Role $role, Admin $assignedBy): void
    {
        $admin->roles()->syncWithPivotValues([$role->id], [
            'assigned_at' => now(),
            'assigned_by' => $assignedBy->id,
        ]);
    }
}
