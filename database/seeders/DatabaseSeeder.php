<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // Executar seeders na ordem correta
        $this->call([
            // Primeiro criar roles e permissões
            RoleSeeder::class,
            PermissionSeeder::class,
            
            // Depois criar admins
            AdminSeeder::class,
            
            // Por último atribuir permissões aos admins
            AdminRolePermissionSeeder::class,
            
            // Outros seeders
            // ChatSeeder::class,
            TestUserSeeder::class,
            AssistantSeeder::class,
        ]);
    }
}
