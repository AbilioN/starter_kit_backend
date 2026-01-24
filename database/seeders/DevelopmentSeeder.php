<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;
use Illuminate\Support\Facades\Hash;

class DevelopmentSeeder extends Seeder
{
    /**
     * Seed the application's database with development data.
     * 
     * This seeder should only be used in development/testing environments.
     * Run with: php artisan db:seed --class=DevelopmentSeeder
     */
    public function run(): void
    {
        $faker = Faker::create();
        
        $this->command->info('Creating 20 random admins...');
        
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
        
        $this->command->info('✅ Created 20 random admins successfully!');
        $this->command->info('Default password for all: password123');
    }
}

