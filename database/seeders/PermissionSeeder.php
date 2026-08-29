<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Admin permissions
        Permission::create([
            'slug' => 'admin-create',
            'name' => 'Create Administrator',
            'description' => 'Allows creating new administrators',
            'resource' => 'admin',
            'action' => 'create',
            'route' => 'admin/create',
        ]);

        Permission::create([
            'slug' => 'admin-read',
            'name' => 'View Administrator',
            'description' => 'Allows viewing administrator information',
            'resource' => 'admin',
            'action' => 'read',
            'route' => 'admin/read',
        ]);

        Permission::create([
            'slug' => 'admin-update',
            'name' => 'Edit Administrator',
            'description' => 'Allows editing administrator information',
            'resource' => 'admin',
            'action' => 'update',
        ]);

        Permission::create([
            'slug' => 'admin-delete',
            'name' => 'Delete Administrator',
            'description' => 'Allows deleting administrators',
            'resource' => 'admin',
            'action' => 'delete',
            'route' => 'admin/delete',
        ]);

        Permission::create([
            'slug' => 'admin-manage',
            'name' => 'Manage Administrators',
            'description' => 'Allows managing all aspects of administrators',
            'resource' => 'admin',
            'action' => 'manage',
            'route' => 'admin/manage',
        ]);

        Permission::create([
            'slug' => 'user-create',
            'name' => 'Create User',
            'description' => 'Allows creating new users',
            'resource' => 'user',
            'action' => 'create',
            'route' => 'user/create',
        ]);

        Permission::create([
            'slug' => 'user-read',
            'name' => 'View User',
            'description' => 'Allows viewing user information',
            'resource' => 'user',
            'action' => 'read',
            'route' => 'user/read',
        ]);

        Permission::create([
            'slug' => 'user-update',
            'name' => 'Edit User',
            'description' => 'Allows editing user information',
            'resource' => 'user',
            'action' => 'update',
        ]);

        Permission::create([
            'slug' => 'user-delete',
            'name' => 'Delete User',
            'description' => 'Allows deleting users',
            'resource' => 'user',
            'action' => 'delete',
            'route' => 'user/delete',
        ]);

        // Chat permissions
        Permission::create([
            'slug' => 'chat-manage',
            'name' => 'Manage Chat',
            'description' => 'Allows managing chats and messages',
            'resource' => 'chat',
            'action' => 'manage',
            'route' => 'chat/manage',
        ]);

        Permission::create([
            'slug' => 'chat-read',
            'name' => 'View Chat',
            'description' => 'Allows viewing chats and messages',
            'resource' => 'chat',
            'action' => 'read',
            'route' => 'chat/read',
        ]);

        // Agenda permissions. Route optimisation is its own slug rather
        // than folded into appointment-read: MADCRM excludes sales reps from
        // it deliberately — the round is planned FOR them, not BY them — and
        // that only stays expressible if the two rights are separable.
        Permission::create([
            'slug' => 'appointment-read',
            'name' => 'View Agenda',
            'description' => 'Allows viewing the agenda and its appointments',
            'resource' => 'appointment',
            'action' => 'read',
            'route' => 'appointment/read',
        ]);

        Permission::create([
            'slug' => 'appointment-create',
            'name' => 'Create Appointments',
            'description' => 'Allows creating appointments',
            'resource' => 'appointment',
            'action' => 'create',
            'route' => 'appointment/create',
        ]);

        Permission::create([
            'slug' => 'appointment-update',
            'name' => 'Update Appointments',
            'description' => 'Allows editing appointments and changing their status',
            'resource' => 'appointment',
            'action' => 'update',
            'route' => 'appointment/update',
        ]);

        Permission::create([
            'slug' => 'appointment-delete',
            'name' => 'Delete Appointments',
            'description' => 'Allows deleting appointments',
            'resource' => 'appointment',
            'action' => 'delete',
            'route' => 'appointment/delete',
        ]);

        Permission::create([
            'slug' => 'route-optimize',
            'name' => 'Optimise Routes',
            'description' => 'Allows computing an optimised route through a set of appointments',
            'resource' => 'route',
            'action' => 'optimize',
            'route' => 'route/optimize',
        ]);

        // Role permissions
        Permission::create([
            'slug' => 'role-assign',
            'name' => 'Assign Roles',
            'description' => 'Allows assigning roles to users and administrators',
            'resource' => 'role',
            'action' => 'assign',
            'route' => 'role/assign',
        ]);

        Permission::create([
            'slug' => 'role-manage',
            'name' => 'Manage Roles',
            'description' => 'Allows creating, editing and deleting roles',
            'resource' => 'role',
            'action' => 'manage',
            'route' => 'role/manage',
        ]);

        Permission::create([
            'slug' => 'role-read',
            'name' => 'View Role',
            'description' => 'Allows viewing roles',
            'resource' => 'role',
            'action' => 'read',
            'route' => 'role/read',
        ]);

        Permission::create([
            'slug' => 'role-delete',
            'name' => 'Delete Role',
            'description' => 'Allows deleting roles',
            'resource' => 'role',
            'action' => 'delete',
            'route' => 'role/delete',
        ]);
        Permission::create([
            'slug' => 'role-create',
            'name' => 'Create Role',
            'description' => 'Allows creating roles',
            'resource' => 'role',
            'action' => 'create',
            'route' => 'role/create',
        ]);

        Permission::create([
            'slug' => 'role-update',
            'name' => 'Update Role',
            'description' => 'Allows updating roles',
            'resource' => 'role',
            'action' => 'update',
            'route' => 'role/update',
        ]);


        Permission::create([
            'slug' => 'role-unassign',
            'name' => 'Unassign Role',
            'description' => 'Allows unassigning roles from users and administrators',
            'resource' => 'role',
            'action' => 'unassign',
            'route' => 'role/unassign',
        ]);

        // Audit Log permissions
        // IMPORTANT: Audit logs are IMMUTABLE for security and compliance
        // Only READ permission exists - NO create, update, or delete permissions
        Permission::create([
            'slug' => 'audit-read',
            'name' => 'View Audit Logs',
            'description' => 'Allows viewing audit logs and system activity history',
            'resource' => 'audit',
            'action' => 'read',
            'route' => 'audit/read',
        ]);

        // Settings permissions
        Permission::create([
            'slug' => 'setting-read',
            'name' => 'View Settings',
            'description' => 'Allows viewing application settings',
            'resource' => 'setting',
            'action' => 'read',
            'route' => 'setting/read',
        ]);

        Permission::create([
            'slug' => 'setting-update',
            'name' => 'Update Settings',
            'description' => 'Allows updating application settings',
            'resource' => 'setting',
            'action' => 'update',
            'route' => 'setting/update',
        ]);

        // File permissions
        Permission::create([
            'slug' => 'file-read',
            'name' => 'View Files',
            'description' => 'Allows viewing and listing uploaded files',
            'resource' => 'file',
            'action' => 'read',
            'route' => 'file/read',
        ]);

        Permission::create([
            'slug' => 'file-upload',
            'name' => 'Upload Files',
            'description' => 'Allows uploading files',
            'resource' => 'file',
            'action' => 'upload',
            'route' => 'file/upload',
        ]);

        Permission::create([
            'slug' => 'file-delete',
            'name' => 'Delete Files',
            'description' => 'Allows deleting files',
            'resource' => 'file',
            'action' => 'delete',
            'route' => 'file/delete',
        ]);

        // Template permissions
        Permission::create([
            'slug' => 'template-read',
            'name' => 'View Templates',
            'description' => 'Allows viewing and listing content templates',
            'resource' => 'template',
            'action' => 'read',
            'route' => 'template/read',
        ]);

        Permission::create([
            'slug' => 'template-create',
            'name' => 'Create Templates',
            'description' => 'Allows creating content templates',
            'resource' => 'template',
            'action' => 'create',
            'route' => 'template/create',
        ]);

        Permission::create([
            'slug' => 'template-update',
            'name' => 'Edit Templates',
            'description' => 'Allows editing content templates',
            'resource' => 'template',
            'action' => 'update',
            'route' => 'template/update',
        ]);

        Permission::create([
            'slug' => 'template-delete',
            'name' => 'Delete Templates',
            'description' => 'Allows deleting content templates',
            'resource' => 'template',
            'action' => 'delete',
            'route' => 'template/delete',
        ]);

        Permission::create([
            'slug' => 'template-manage',
            'name' => 'Manage Templates',
            'description' => 'Allows managing all aspects of content templates',
            'resource' => 'template',
            'action' => 'manage',
            'route' => 'template/manage',
        ]);
    }
}
