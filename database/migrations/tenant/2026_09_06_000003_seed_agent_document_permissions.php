<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Puts the two agent-document permission slugs into tenants that already exist.
 *
 * Same mechanism and same reason as the custom-field pair beside this file:
 * permissions are rows in each tenant's own database, PermissionSeeder runs
 * once inside ProvisionTenantUseCase, there is no `tenant:seed`, and a slug
 * added to that seeder therefore reaches new tenants and no existing one. The
 * gap is invisible in testing, because a fresh tenant's only admin is a super
 * admin and CheckAdminPermissionUseCase short-circuits before consulting any
 * role.
 *
 * Two slugs, split read from write on purpose. Reading is what the assistant's
 * search tool holds; writing is what uploads a PDF, extracts its text and
 * decides whether it is `internal` or `published` — a different privilege, and
 * the one that can expose a supplier contract to every customer.
 */
return new class extends Migration
{
    protected $connection = 'tenant';

    /** Mirrors Database\Seeders\PermissionSeeder's shape. */
    private const PERMISSIONS = [
        [
            'slug' => 'document-read',
            'name' => 'View Agent Documents',
            'description' => 'Allows reading the documents the assistant can search, including internal ones',
            'resource' => 'document',
            'action' => 'read',
            'route' => 'document/read',
        ],
        [
            'slug' => 'document-manage',
            'name' => 'Manage Agent Documents',
            'description' => 'Allows adding, editing and removing documents, and deciding whether each is internal or published to end users',
            'resource' => 'document',
            'action' => 'manage',
            'route' => 'document/manage',
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
