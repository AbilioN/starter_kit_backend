<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Assistant;

class AssistantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Criar assistentes padrão
        Assistant::create([
            'name' => 'AI Assistant',
            'description' => 'Assistente de IA para conversas gerais',
            'capabilities' => ['chat', 'ai_response', 'general_knowledge'],
            'is_active' => true
        ]);

        Assistant::create([
            'name' => 'Support Bot',
            'description' => 'Bot de suporte para dúvidas técnicas',
            'capabilities' => ['chat', 'support', 'technical_help'],
            'is_active' => true
        ]);

        Assistant::create([
            'name' => 'Creative Helper',
            'description' => 'Assistente criativo para ideias e brainstorming',
            'capabilities' => ['chat', 'creative', 'brainstorming'],
            'is_active' => true
        ]);
    }
}
