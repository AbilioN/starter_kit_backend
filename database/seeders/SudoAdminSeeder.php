<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SudoAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Admin::create([
            'name' => 'Super Admin',
            'email' => 'admin@dashboard.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
            'is_super_admin' => true,
        ]);
    }
}
