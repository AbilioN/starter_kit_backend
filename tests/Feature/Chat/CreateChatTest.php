<?php

namespace Tests\Feature\Chat;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateChatTest extends TestCase
{
    use RefreshDatabase;

    protected $user1;
    protected $user2;
    protected $admin;

    protected function setUp(): void
    {
        parent::setUp();
        // Cria usuários e admin
        $this->user1 = User::factory()->create();
        $this->user2 = User::factory()->create();
        
        // Criar Admin com ID específico usando insert direto para evitar conflitos
        \DB::table('admins')->insert([
            'id' => 2,
            'name' => 'Test Admin',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'is_active' => true,
            'is_super_admin' => false,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        $this->admin = Admin::find(2);
    }

    public function test_user_can_create_private_chat_with_admin()
    {
        $token = $this->user1->createToken('test')->plainTextToken;
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/chat/create-private', [
                'other_user_id' => $this->admin->id,
                'other_user_type' => 'admin'
            ]);
        
        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => ['id', 'type', 'name', 'description']
            ]);
    }

    public function test_admin_can_create_private_chat_with_user()
    {
        $token = $this->admin->createToken('test')->plainTextToken;
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/chat/create-private', [
                'other_user_id' => $this->user2->id,
                'other_user_type' => 'user'
            ]);
        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => ['id', 'type', 'name', 'description']
            ]);
    }

    public function test_user_can_create_group_chat_with_admin_and_user()
    {
        $token = $this->user1->createToken('test')->plainTextToken;
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/chat/create-group', [
                'name' => 'Grupo Teste',
                'description' => 'Grupo de teste',
                'participants' => [
                    ['user_id' => $this->admin->id, 'user_type' => 'admin'],
                    ['user_id' => $this->user2->id, 'user_type' => 'user']
                ]
            ]);
        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => ['id', 'type', 'name', 'description']
            ]);
        // Note: participants não estão na resposta direta, são adicionados separadamente
    }

    public function test_create_group_chat_requires_participants()
    {
        $token = $this->user1->createToken('test')->plainTextToken;
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/chat/create-group', [
                'name' => 'Grupo Teste',
                'description' => 'Grupo de teste',
                'participants' => []
            ]);
        $response->assertStatus(422);
    }

    public function test_create_private_chat_requires_other_user()
    {
        $token = $this->user1->createToken('test')->plainTextToken;
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/chat/create-private', []);
        
        $response->assertStatus(422);
        $response->assertJson([
            'success' => false
        ]);
    }

    public function test_returns_existing_private_chat_when_already_exists()
    {
        $token = $this->user1->createToken('test')->plainTextToken;
        
        // Primeira criação
        $response1 = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/chat/create-private', [
                'other_user_id' => $this->admin->id,
                'other_user_type' => 'admin'
            ]);
        
        $chatId1 = $response1->json('data.chat.id');
        
        // Segunda criação (deve retornar o mesmo chat)
        $response2 = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/chat/create-private', [
                'other_user_id' => $this->admin->id,
                'other_user_type' => 'admin'
            ]);
        
        $chatId2 = $response2->json('data.chat.id');
        
        // Verifica que o mesmo chat foi retornado
        $this->assertEquals($chatId1, $chatId2);
        
        // Verifica que apenas um chat foi criado no banco
        $this->assertDatabaseCount('chats', 1);
    }

    public function test_returns_existing_private_chat_from_other_user_perspective()
    {
        $token1 = $this->user1->createToken('test')->plainTextToken;
        $token2 = $this->admin->createToken('test')->plainTextToken;
        
        // Criação pelo user1
        $response1 = $this->withHeader('Authorization', 'Bearer ' . $token1)
            ->postJson('/api/chat/create-private', [
                'other_user_id' => $this->admin->id,
                'other_user_type' => 'admin'
            ]);
        
        $chatId1 = $response1->json('data.chat.id');
        
        // Busca pelo admin (deve retornar o mesmo chat)
        $response2 = $this->withHeader('Authorization', 'Bearer ' . $token2)
            ->postJson('/api/chat/create-private', [
                'other_user_id' => $this->user1->id,
                'other_user_type' => 'user'
            ]);
        
        $chatId2 = $response2->json('data.chat.id');
        
        // Verifica que o mesmo chat foi retornado
        $this->assertEquals($chatId1, $chatId2);
        
        // Verifica que apenas um chat foi criado no banco
        $this->assertDatabaseCount('chats', 1);
    }

    public function test_created_by_type_is_derived_from_chat_user_table()
    {
        $token = $this->user1->createToken('test')->plainTextToken;
        
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/chat/create-private', [
                'other_user_id' => $this->admin->id,
                'other_user_type' => 'admin'
            ]);
        
        $response->assertStatus(201);
        
        $chatId = $response->json('data.id');
        
        // Verifica que o chat foi criado sem created_by_type
        $this->assertDatabaseHas('chats', [
            'id' => $chatId,
            'created_by' => $this->user1->id
        ]);
        
        // Verifica que não há campo created_by_type
        $chat = \Illuminate\Support\Facades\DB::table('chats')->where('id', $chatId)->first();
        $this->assertObjectNotHasProperty('created_by_type', $chat);
        
        // Verifica que o tipo do criador está na tabela chat_user
        $creatorParticipant = \Illuminate\Support\Facades\DB::table('chat_user')
            ->where('chat_id', $chatId)
            ->where('user_id', $this->user1->id)
            ->where('user_type', 'user') // Adicionado user_type para evitar conflitos
            ->first();
            
        $this->assertNotNull($creatorParticipant);
        $this->assertEquals('user', $creatorParticipant->user_type);
    }
} 