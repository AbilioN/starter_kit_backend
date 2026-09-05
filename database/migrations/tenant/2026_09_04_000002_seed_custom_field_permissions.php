<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Puts the two custom-field permission slugs into tenants that already exist.
 *
 * Permissions are rows in each tenant's own database, and the only thing that
 * creates them is PermissionSeeder, which runs once inside
 * ProvisionTenantUseCase. There is no `tenant:seed`. So a new slug added to
 * that seeder reaches new tenants and no existing one — and the gap is
 * invisible, because a fresh tenant's only admin is a super admin and
 * CheckAdminPermissionUseCase short-circuits on isSuperAdmin() before
 * consulting any role. The feature would look fine to whoever tested it and
 * be unusable for the first ordinary admin a tenant creates.
 *
 * A migration is the only mechanism that reaches existing tenants here,
 * because `tenant:migrate` is already the step an operator runs after a
 * deploy.
 *
 * Idempotent on purpose, in both directions: it is safe beside
 * PermissionSeeder (which also firstOrCreate's these two), and safe to re-run
 * against a tenant that already has them.
 *
 * Two slugs, not four. Creating a definition runs DDL against the tenant's own
 * database — that is one privilege, not a create/update/delete triad. And
 * there is deliberately no permission row PER custom field:
 * RolePermissionSeeder syncs *every* permission onto super-admin and admin, so
 * a per-field permission would auto-grant to exactly the roles a tenant is
 * trying to hide the field from.
 */
return new class extends Migration
{
    protected $connection = 'tenant';

    /** Mirrors Database\Seeders\PermissionSeeder's shape. */
    private const PERMISSIONS = [
        [
            'slug' => 'custom-field-read',
            'name' => 'View Custom Fields',
            'description' => 'Allows viewing custom field definitions',
            'resource' => 'custom-field',
            'action' => 'read',
            'route' => 'custom-field/read',
        ],
        [
            'slug' => 'custom-field-manage',
            'name' => 'Manage Custom Fields',
            'description' => 'Allows creating, editing and retiring custom fields, which changes the tenant database schema',
            'resource' => 'custom-field',
            'action' => 'manage',
            'route' => 'custom-field/manage',
        ],
    ];

    /** Matches RolePermissionSeeder::FULL_ACCESS_ROLES. */
    private const FULL_ACCESS_ROLES = ['super-admin', 'admin'];

    public function up(): void
    {
        $connection = $this->getConnection();
        $now = now();
        $ids = [];

        foreach (self::PERMISSIONS as $permission) {
            $existing = DB::connection($connection)->table('permissions')
                ->where('slug', $permission['slug'])
                ->value('id');

            if ($existing !== null) {
                $ids[] = $existing;

                continue;
            }

            $id = (string) Str::uuid();

            DB::connection($connection)->table('permissions')->insert([
                'id' => $id,
                ...$permission,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $ids[] = $id;
        }

        // Grant to the full-access roles, the way RolePermissionSeeder does.
        // Without this the rows exist and nobody holds them, which is the
        // "roles carrying zero permissions" bug that seeder was written for.
        foreach (self::FULL_ACCESS_ROLES as $slug) {
            $roleId = DB::connection($connection)->table('roles')->where('slug', $slug)->value('id');

            if ($roleId === null) {
                continue;
            }

            foreach ($ids as $permissionId) {
                $alreadyGranted = DB::connection($connection)->table('role_permissions')
                    ->where('role_id', $roleId)
                    ->where('permission_id', $permissionId)
                    ->exists();

                if ($alreadyGranted) {
                    continue;
                }

                // role_permissions uses an auto-increment id() and carries its
                // own is_active flag — it is not shaped like the uuid tables
                // around it.
                DB::connection($connection)->table('role_permissions')->insert([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        $connection = $this->getConnection();

        $ids = DB::connection($connection)->table('permissions')
            ->whereIn('slug', array_column(self::PERMISSIONS, 'slug'))
            ->pluck('id');

        DB::connection($connection)->table('role_permissions')->whereIn('permission_id', $ids)->delete();
        DB::connection($connection)->table('permissions')->whereIn('id', $ids)->delete();
    }
};
