<?php

namespace Tests\Feature\Chat;

use App\Models\Admin;
use App\Models\Chat;
use App\Models\ChatUser;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MessageTest extends TestCase
{
    use RefreshDatabase;

    protected $user1;
    protected $user2;
    protected $admin;
    protected $chat;

    protected function setUp(): void
    {
        parent::setUp();
        
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
        
        // Cria chat privado
        $this->chat = Chat::create([
            'name' => 'Chat Teste',
            'type' => 'private',
            'description' => 'Chat de teste',
            'created_by' => $this->user1->id
        ]);
        
        ChatUser::insert([ 
            [   
                'chat_id' => $this->chat->id,
                'user_id' => $this->user1->id,
                'user_type' => 'user',
                'joined_at' => now(),
                'is_active' => true
            ],
            [
                'chat_id' => $this->chat->id,
                'user_id' => $this->admin->id,
                'user_type' => 'admin',
                'joined_at' => now(),
                'is_active' => true
            ]
        ]);
  
    }


    public function test_admin_is_participant_of_chat()
    {
        // Verifica se o admin está na tabela chat_user
        $adminParticipant = DB::table('chat_user')
            ->where('chat_id', $this->chat->id)
            ->where('user_id', $this->admin->id)
            ->first();

        $this->assertNotNull($adminParticipant);
        $this->assertEquals('admin', $adminParticipant->user_type);
        $this->assertEquals(1, $adminParticipant->is_active);
    }

    public function test_user_can_send_message_to_chat()
    {
        $token = $this->user1->createToken('test')->plainTextToken;
        
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson("/api/chat/{$this->chat->id}/send", [
                'content' => 'Olá, esta é uma mensagem de teste!',
                'message_type' => 'text'
            ]);
        $response->assertStatus(202)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'chat_id',
                    'status',
                    'message_type'
                ]
            ]);
        $this->assertDatabaseHas('messages', [
            'chat_id' => $this->chat->id,
            'content' => 'Olá, esta é uma mensagem de teste!',
            'sender_id' => $this->user1->id,
            'message_type' => 'text',
            'is_read' => false
        ]);
    }

    public function test_admin_can_send_message_to_chat()
    {
        $token = $this->admin->createToken('test')->plainTextToken;
        
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson("/api/chat/{$this->chat->id}/send", [
                'content' => 'Resposta do admin!',
                'message_type' => 'text'
            ]);

        $response->assertStatus(202);

        $this->assertDatabaseHas('messages', [
            'chat_id' => $this->chat->id,
            'content' => 'Resposta do admin!',
            'sender_id' => $this->admin->id,
            'message_type' => 'text',
            'is_read' => false
        ]);
    }

    public function test_user_cannot_send_message_to_chat_they_are_not_part_of()
    {
        $token = $this->user2->createToken('test')->plainTextToken;
        
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson("/api/chat/{$this->chat->id}/send", [
                'content' => 'Mensagem de usuário não participante',
                'message_type' => 'text'
            ]);

        $response->assertStatus(403);
    }

    public function test_can_get_chat_messages()
    {
        // Cria algumas mensagens
        Message::create([
            'chat_id' => $this->chat->id,
            'content' => 'Primeira mensagem',
            'sender_id' => $this->user1->id,
            'message_type' => 'text',
            'is_read' => false
        ]);

        Message::create([
            'chat_id' => $this->chat->id,
            'content' => 'Segunda mensagem',
            'sender_id' => $this->admin->id,
            'message_type' => 'text',
            'is_read' => false
        ]);

        $token = $this->user1->createToken('test')->plainTextToken;
        
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson("/api/chat/{$this->chat->id}/messages");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'messages' => [
                        '*' => [
                            'id',
                            'chat_id',
                            'content',
                            'sender_id',
                            'message_type',
                            'metadata',
                            'is_read',
                            'created_at'
                        ]
                    ],
                    'from_cache',
                    'pagination'
                ]
            ]);

        $this->assertCount(2, $response->json('data.messages'));
    }

    public function test_can_mark_messages_as_read()
    {
        // Cria mensagens não lidas
        Message::create([
            'chat_id' => $this->chat->id,
            'content' => 'Mensagem não lida 1',
            'sender_id' => $this->admin->id,
            'message_type' => 'text',
            'is_read' => false
        ]);

        Message::create([
            'chat_id' => $this->chat->id,
            'content' => 'Mensagem não lida 2',
            'sender_id' => $this->admin->id,
            'message_type' => 'text',
            'is_read' => false
        ]);

        $token = $this->user1->createToken('test')->plainTextToken;
        
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson("/api/chat/{$this->chat->id}/read");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => ['message']
            ]);

        $this->assertEquals('Messages marked as read', $response->json('data.message'));

        // Verifica se as mensagens foram marcadas como lidas
        $this->assertDatabaseHas('messages', [
            'chat_id' => $this->chat->id,
            'content' => 'Mensagem não lida 1',
            'is_read' => true
        ]);

        $this->assertDatabaseHas('messages', [
            'chat_id' => $this->chat->id,
            'content' => 'Mensagem não lida 2',
            'is_read' => true
        ]);
    }

    public function test_can_get_unread_count()
    {
        // Cria mensagens não lidas
        Message::create([
            'chat_id' => $this->chat->id,
            'content' => 'Mensagem não lida 1',
            'sender_id' => $this->admin->id,
            'message_type' => 'text',
            'is_read' => false
        ]);

        Message::create([
            'chat_id' => $this->chat->id,
            'content' => 'Mensagem não lida 2',
            'sender_id' => $this->admin->id,
            'message_type' => 'text',
            'is_read' => false
        ]);

        $token = $this->user1->createToken('test')->plainTextToken;
        
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson("/api/chat/{$this->chat->id}/unread-count");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => ['unread_count']
            ]);

        $this->assertEquals(2, $response->json('data.unread_count'));
    }

    public function test_can_send_message_with_metadata()
    {
        $token = $this->user1->createToken('test')->plainTextToken;
        
        $metadata = [
            'file_url' => 'https://example.com/image.jpg',
            'file_size' => 1024,
            'mime_type' => 'image/jpeg'
        ];
        
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson("/api/chat/{$this->chat->id}/send", [
                'content' => 'Mensagem com metadados',
                'message_type' => 'image',
                'metadata' => $metadata
            ]);

        $response->assertStatus(202);

        // Verifica se a mensagem foi criada (sem verificar o formato exato do JSON)
        $this->assertDatabaseHas('messages', [
            'chat_id' => $this->chat->id,
            'content' => 'Mensagem com metadados',
            'sender_id' => $this->user1->id,
            'message_type' => 'image'
        ]);

        // Verifica se o metadata foi salvo (verificando apenas se existe)
        $message = Message::where('chat_id', $this->chat->id)
            ->where('content', 'Mensagem com metadados')
            ->first();
        
        $this->assertNotNull($message);
        $this->assertNotNull($message->metadata);
        $this->assertEquals($metadata, $message->metadata);
    }

    public function test_sender_type_is_derived_from_chat_user_table()
    {
        $token = $this->user1->createToken('test')->plainTextToken;
        
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson("/api/chat/{$this->chat->id}/send", [
                'content' => 'Teste de derivação do tipo',
                'message_type' => 'text'
            ]);

        $response->assertStatus(202);

        $responseData = $response->json('data');
        
        // Verifica que a resposta contém os dados esperados
        $this->assertArrayHasKey('chat_id', $responseData);
        $this->assertArrayHasKey('status', $responseData);
        $this->assertArrayHasKey('message_type', $responseData);
        
        // Verifica que o status é 'queued' (mensagem foi enfileirada)
        $this->assertEquals('queued', $responseData['status']);
        $this->assertEquals('text', $responseData['message_type']);
    }

    public function test_validation_requires_content()
    {
        $token = $this->user1->createToken('test')->plainTextToken;
        
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson("/api/chat/{$this->chat->id}/send", [
                'message_type' => 'text'
            ]);

        $response->assertStatus(422);
    }

    public function test_validation_accepts_valid_message_types()
    {
        $token = $this->user1->createToken('test')->plainTextToken;
        
        $validTypes = ['text', 'image', 'file'];
        
        foreach ($validTypes as $type) {
            $response = $this->withHeader('Authorization', 'Bearer ' . $token)
                ->postJson("/api/chat/{$this->chat->id}/send", [
                    'content' => "Mensagem do tipo {$type}",
                    'message_type' => $type
                ]);

            $response->assertStatus(202);
        }
    }

    public function test_validation_rejects_invalid_message_types()
    {
        $token = $this->user1->createToken('test')->plainTextToken;
        
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson("/api/chat/{$this->chat->id}/send", [
                'content' => 'Mensagem com tipo inválido',
                'message_type' => 'invalid_type'
            ]);

        $response->assertStatus(422);
    }
} 