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

    }
}
