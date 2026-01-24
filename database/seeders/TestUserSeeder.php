<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TestUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create test user for frontend
        User::create([
            'name' => 'Test User',
            'email' => 'user@dashboard.com',
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
        ]);

        // Create some additional users for testing
        User::create([
            'name' => 'João Silva',
            'email' => 'joao@dashboard.com',
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
        ]);

        User::create([
            'name' => 'Maria Santos',
            'email' => 'maria@dashboard.com',
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
        ]);

        $this->command->info('✅ Test users created successfully!');
        $this->command->info('📧 Email: user@dashboard.com | Password: password123');
        $this->command->info('📧 Email: joao@dashboard.com | Password: password123');
        $this->command->info('📧 Email: maria@dashboard.com | Password: password123');
    }
} 