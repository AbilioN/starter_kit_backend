<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Admin;
use Illuminate\Support\Facades\Hash;
use Faker\Factory as Faker;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // First create the Super Admin
        $this->call(SudoAdminSeeder::class);
        
        $faker = Faker::create();
        
        // Create 20 regular admins with Faker
        for ($i = 1; $i <= 20; $i++) {
            Admin::create([
                'name' => $faker->name(),
                'email' => $faker->unique()->safeEmail(),
                'password' => Hash::make('password123'),
                'is_active' => $faker->boolean(85), // 85% chance of being active
                'is_super_admin' => false,
            ]);
        }
    }
}
