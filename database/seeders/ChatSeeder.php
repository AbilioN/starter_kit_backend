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
        $user1 = User::where('email', 'joao@dashboard.com')->firstOrFail();
        $user2 = User::where('email', 'maria@dashboard.com')->firstOrFail();
        $admin = Admin::where('is_super_admin', true)->firstOrFail();

        // Private chat: João ↔ Admin
        $chat1 = Chat::create([
            'type' => 'private',
            'name' => 'Chat João - Admin',
            'description' => 'Chat privado entre João e Admin',
        ]);

        $chat1->users()->attach($user1->id, ['user_type' => 'user', 'joined_at' => now(), 'is_active' => true]);
        $chat1->users()->attach($admin->id, ['user_type' => 'admin', 'joined_at' => now(), 'is_active' => true]);

        // Private chat: Maria ↔ Admin
        $chat2 = Chat::create([
            'type' => 'private',
            'name' => 'Chat Maria - Admin',
            'description' => 'Chat privado entre Maria e Admin',
        ]);

        $chat2->users()->attach($user2->id, ['user_type' => 'user', 'joined_at' => now(), 'is_active' => true]);
        $chat2->users()->attach($admin->id, ['user_type' => 'admin', 'joined_at' => now(), 'is_active' => true]);

        Message::create([
            'chat_id' => $chat1->id,
            'content' => 'Olá! Preciso de ajuda com minha conta.',
            'sender_type' => 'user',
            'sender_id' => $user1->id,
            'is_read' => true,
        ]);

        Message::create([
            'chat_id' => $chat1->id,
            'content' => 'Olá João! Como posso te ajudar?',
            'sender_type' => 'admin',
            'sender_id' => $admin->id,
            'is_read' => true,
        ]);

        Message::create([
            'chat_id' => $chat1->id,
            'content' => 'Não consigo fazer login no sistema.',
            'sender_type' => 'user',
            'sender_id' => $user1->id,
            'is_read' => false,
        ]);

        Message::create([
            'chat_id' => $chat2->id,
            'content' => 'Bom dia! Tenho uma dúvida sobre o produto.',
            'sender_type' => 'user',
            'sender_id' => $user2->id,
            'is_read' => true,
        ]);

        Message::create([
            'chat_id' => $chat2->id,
            'content' => 'Bom dia Maria! Qual é sua dúvida?',
            'sender_type' => 'admin',
            'sender_id' => $admin->id,
            'is_read' => false,
        ]);

        $this->command->info('✅ Chat seeder executado com sucesso!');
        $this->command->info('- 2 chats privados criados');
        $this->command->info('- 5 mensagens de exemplo criadas');
    }
}
