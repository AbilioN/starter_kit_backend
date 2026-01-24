<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        
        Role::create([
            'slug' => 'super-admin',
            'name' => 'Super Admin',
            'description' => 'System Super Administrator',
        ]);

        Role::create([
            'slug' => 'admin',
            'name' => 'Administrator',
            'description' => 'System Administrator',
        ]);

        Role::create([
            'slug' => 'user',
            'name' => 'User',
            'description' => 'Regular user',
        ]);
    }
}
