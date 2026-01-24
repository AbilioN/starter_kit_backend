<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Chat;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Seeder;

class ChatSeeder extends Seeder
{
    public function run(): void
    {
        // Criar alguns usuários e admins se não existirem
        $user1 = User::firstOrCreate(
            ['email' => 'user1@example.com'],
            [
                'name' => 'João Silva',
                'email' => 'user1@example.com',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]
        );

        $user2 = User::firstOrCreate(
            ['email' => 'user2@example.com'],
            [
                'name' => 'Maria Santos',
                'email' => 'user2@example.com',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]
        );

        $admin = Admin::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin Suporte',
                'email' => 'admin@example.com',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]
        );

        // Criar chat privado entre user1 e admin
        $chat1 = Chat::create([
            'type' => 'private',
            'name' => 'Chat João - Admin',
            'description' => 'Chat privado entre João e Admin'
        ]);

        $chat1->users()->attach([
            $user1->id => ['user_type' => 'user'],
            $admin->id => ['user_type' => 'admin']
        ]);

        // Criar chat privado entre user2 e admin
        $chat2 = Chat::create([
            'type' => 'private',
            'name' => 'Chat Maria - Admin',
            'description' => 'Chat privado entre Maria e Admin'
        ]);

        $chat2->users()->attach([
            $user2->id => ['user_type' => 'user'],
            $admin->id => ['user_type' => 'admin']
        ]);

        // Criar algumas mensagens de exemplo
        Message::create([
            'chat_id' => $chat1->id,
            'content' => 'Olá! Preciso de ajuda com minha conta.',
            'sender_type' => 'user',
            'sender_id' => $user1->id,
            'is_read' => true
        ]);

        Message::create([
            'chat_id' => $chat1->id,
            'content' => 'Olá João! Como posso te ajudar?',
            'sender_type' => 'admin',
            'sender_id' => $admin->id,
            'is_read' => true
        ]);

        Message::create([
            'chat_id' => $chat1->id,
            'content' => 'Não consigo fazer login no sistema.',
            'sender_type' => 'user',
            'sender_id' => $user1->id,
            'is_read' => false
        ]);

        Message::create([
            'chat_id' => $chat2->id,
            'content' => 'Bom dia! Tenho uma dúvida sobre o produto.',
            'sender_type' => 'user',
            'sender_id' => $user2->id,
            'is_read' => true
        ]);

        Message::create([
            'chat_id' => $chat2->id,
            'content' => 'Bom dia Maria! Qual é sua dúvida?',
            'sender_type' => 'admin',
            'sender_id' => $admin->id,
            'is_read' => false
        ]);

        $this->command->info('Chat seeder executado com sucesso!');
        $this->command->info('Criados:');
        $this->command->info('- 2 chats privados');
        $this->command->info('- 5 mensagens de exemplo');
    }
} 