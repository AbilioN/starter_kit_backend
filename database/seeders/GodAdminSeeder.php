<?php

namespace Database\Seeders;

use App\Models\GodAdmin;
use Illuminate\Database\Seeder;

/**
 * Landlord seeder, not tenant - GodAdmin doesn't exist inside
 * DatabaseSeeder's tenant-scoped run() chain. Invoke directly:
 * php artisan db:seed --class=GodAdminSeeder
 */
class GodAdminSeeder extends Seeder
{
    public function run(): void
    {
        GodAdmin::firstOrCreate(
            ['email' => 'god@starterkit.test'],
            [
                'name' => 'God Admin',
                'password' => 'password123',
            ],
        );
    }
}
