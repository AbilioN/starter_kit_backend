<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AdminRolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Buscar todos os admins
        $superAdmin = Admin::where('email', 'admin@dashboard.com')->first();
        $admin = Admin::where('email', 'admin2@dashboard.com')->first();

        // Buscar todas as permissões
        $permissions = Permission::all();

        // Buscar roles
        $adminRole = Role::where('slug', 'admin')->first();
        $superAdminRole = Role::where('slug', 'super-admin')->first();

        // Atribuir todas as permissões às roles
        if (!$permissions->isEmpty()) {
            // Atribuir todas as permissões à role super-admin
            if ($superAdminRole) {
                $superAdminRole->permissions()->sync($permissions->pluck('id')->toArray());
                $this->command->info("✅ All permissions assigned to 'super-admin' role");
            }

            // Assign all permissions to admin role
            if ($adminRole) {
                $adminRole->permissions()->sync($permissions->pluck('id')->toArray());
                $this->command->info("✅ All permissions assigned to 'admin' role");
            }
        } else {
            $this->command->warn("⚠️ No permissions found. Run PermissionSeeder first.");
        }

        if ($adminRole && $superAdminRole) {
            // Assign admin role to secondary admin
            if ($admin) {
                $admin->roles()->syncWithPivotValues([$adminRole->id], [
                    'assigned_at' => now(),
                    'assigned_by' => $superAdmin->id
                ]);
                $this->command->info("✅ 'admin' role assigned to Secondary Admin");
            }
            if ($superAdmin) {
                $superAdmin->roles()->syncWithPivotValues([$superAdminRole->id], [
                    'assigned_at' => now(),
                    'assigned_by' => $superAdmin->id
                ]);
                $this->command->info("✅ 'super-admin' role assigned to Super Admin");
            }
        } else {
            $this->command->warn("⚠️ Roles not found. Run RoleSeeder first.");
        }
    }
}
