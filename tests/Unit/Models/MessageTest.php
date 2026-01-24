<?php

namespace Tests\Unit\Models;

use App\Models\Admin;
use App\Models\Chat;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessageTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $admin;
    protected $chat;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->admin = Admin::factory()->create();
        
        $this->chat = Chat::create([
            'name' => 'Chat Teste',
            'type' => 'private',
            'created_by' => $this->user->id
        ]);
        
        // Adiciona participantes (cada um com seu ID único)
        \Illuminate\Support\Facades\DB::table('chat_user')->insert([
            [
                'chat_id' => $this->chat->id,
                'user_id' => $this->user->id,
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

    public function test_can_create_text_message()
    {
        $message = Message::create([
            'chat_id' => $this->chat->id,
            'content' => 'Olá, mundo!',
            'sender_id' => $this->user->id,
            'message_type' => 'text',
            'is_read' => false
        ]);

        $this->assertDatabaseHas('messages', [
            'id' => $message->id,
            'chat_id' => $this->chat->id,
            'content' => 'Olá, mundo!',
            'sender_id' => $this->user->id,
            'message_type' => 'text',
            'is_read' => false
        ]);

        $this->assertEquals('text', $message->message_type);
        $this->assertFalse($message->is_read);
        $this->assertNull($message->metadata);
    }

    public function test_can_create_image_message_with_metadata()
    {
        $metadata = [
            'file_url' => 'https://example.com/image.jpg',
            'file_size' => 1024,
            'mime_type' => 'image/jpeg'
        ];

        $message = Message::create([
            'chat_id' => $this->chat->id,
            'content' => 'Imagem enviada',
            'sender_id' => $this->admin->id,
            'message_type' => 'image',
            'metadata' => $metadata,
            'is_read' => false
        ]);

        $this->assertDatabaseHas('messages', [
            'id' => $message->id,
            'chat_id' => $this->chat->id,
            'content' => 'Imagem enviada',
            'sender_id' => $this->admin->id,
            'message_type' => 'image',
            'is_read' => false
        ]);

        $this->assertEquals('image', $message->message_type);
        $this->assertEquals($metadata, $message->metadata);
    }

    public function test_can_create_file_message()
    {
        $metadata = [
            'file_url' => 'https://example.com/document.pdf',
            'file_size' => 2048,
            'mime_type' => 'application/pdf',
            'file_name' => 'document.pdf'
        ];

        $message = Message::create([
            'chat_id' => $this->chat->id,
            'content' => 'Documento enviado',
            'sender_id' => $this->user->id,
            'message_type' => 'file',
            'metadata' => $metadata,
            'is_read' => false
        ]);

        $this->assertEquals('file', $message->message_type);
        $this->assertEquals($metadata, $message->metadata);
    }

    public function test_can_mark_message_as_read()
    {
        $message = Message::create([
            'chat_id' => $this->chat->id,
            'content' => 'Mensagem não lida',
            'sender_id' => $this->admin->id,
            'message_type' => 'text',
            'is_read' => false
        ]);

        $message->update([
            'is_read' => true,
            'read_at' => now()
        ]);

        $this->assertTrue($message->fresh()->is_read);
        $this->assertNotNull($message->fresh()->read_at);
    }

    public function test_can_query_messages_by_chat()
    {
        // Cria mensagens
        Message::create([
            'chat_id' => $this->chat->id,
            'content' => 'Mensagem 1',
            'sender_id' => $this->user->id,
            'message_type' => 'text',
            'is_read' => false
        ]);

        Message::create([
            'chat_id' => $this->chat->id,
            'content' => 'Mensagem 2',
            'sender_id' => $this->admin->id,
            'message_type' => 'text',
            'is_read' => false
        ]);

        $messages = Message::where('chat_id', $this->chat->id)->get();
        $this->assertCount(2, $messages);
    }

    public function test_can_query_messages_by_sender()
    {
        // Cria mensagens
        Message::create([
            'chat_id' => $this->chat->id,
            'content' => 'Mensagem do usuário',
            'sender_id' => $this->user->id,
            'sender_type' => 'user',
            'message_type' => 'text',
            'is_read' => false
        ]);

        Message::create([
            'chat_id' => $this->chat->id,
            'content' => 'Mensagem do admin',
            'sender_id' => $this->admin->id,
            'sender_type' => 'admin',
            'message_type' => 'text',
            'is_read' => false
        ]);

        $userMessages = Message::where('sender_id', $this->user->id)->where('sender_type', 'user')->get();
        $this->assertCount(1, $userMessages);
        $this->assertEquals('Mensagem do usuário', $userMessages->first()->content);

        $adminMessages = Message::where('sender_id', $this->admin->id)->where('sender_type', 'admin')->get();
        $this->assertCount(1, $adminMessages);
        $this->assertEquals('Mensagem do admin', $adminMessages->first()->content);
    }

    public function test_can_query_unread_messages()
    {
        // Cria mensagens lidas e não lidas
        Message::create([
            'chat_id' => $this->chat->id,
            'content' => 'Mensagem não lida 1',
            'sender_id' => $this->admin->id,
            'sender_type' => 'admin',
            'message_type' => 'text',
            'is_read' => false
        ]);

        Message::create([
            'chat_id' => $this->chat->id,
            'content' => 'Mensagem lida',
            'sender_id' => $this->admin->id,
            'message_type' => 'text',
            'sender_type' => 'admin',
            'is_read' => true,
            'read_at' => now()
        ]);

        Message::create([
            'chat_id' => $this->chat->id,
            'content' => 'Mensagem não lida 2',
            'sender_id' => $this->admin->id,
            'sender_type' => 'admin',
            'message_type' => 'text',
            'is_read' => false
        ]);

        $unreadMessages = Message::where('chat_id', $this->chat->id)
            ->where('is_read', false)
            ->get();

        $this->assertCount(2, $unreadMessages);
    }

    public function test_can_query_messages_by_type()
    {
        // Cria mensagens de diferentes tipos
        Message::create([
            'chat_id' => $this->chat->id,
            'content' => 'Texto simples',
            'sender_id' => $this->user->id,
            'message_type' => 'text',
            'is_read' => false
        ]);

        Message::create([
            'chat_id' => $this->chat->id,
            'content' => 'Imagem',
            'sender_id' => $this->user->id,
            'message_type' => 'image',
            'metadata' => ['file_url' => 'https://example.com/image.jpg'],
            'is_read' => false
        ]);

        Message::create([
            'chat_id' => $this->chat->id,
            'content' => 'Arquivo',
            'sender_id' => $this->user->id,
            'message_type' => 'file',
            'metadata' => ['file_url' => 'https://example.com/file.pdf'],
            'is_read' => false
        ]);

        $textMessages = Message::where('message_type', 'text')->get();
        $this->assertCount(1, $textMessages);

        $imageMessages = Message::where('message_type', 'image')->get();
        $this->assertCount(1, $imageMessages);

        $fileMessages = Message::where('message_type', 'file')->get();
        $this->assertCount(1, $fileMessages);
    }

    public function test_can_order_messages_by_created_at()
    {
        // Cria mensagens com timestamps diferentes
        $message1 = Message::create([
            'chat_id' => $this->chat->id,
            'content' => 'Primeira mensagem',
            'sender_id' => $this->user->id,
            'message_type' => 'text',
            'is_read' => false,
            'created_at' => now()->subMinutes(2)
        ]);

        $message2 = Message::create([
            'chat_id' => $this->chat->id,
            'content' => 'Segunda mensagem',
            'sender_id' => $this->admin->id,
            'message_type' => 'text',
            'is_read' => false,
            'created_at' => now()->subMinute()
        ]);

        $message3 = Message::create([
            'chat_id' => $this->chat->id,
            'content' => 'Terceira mensagem',
            'sender_id' => $this->user->id,
            'message_type' => 'text',
            'is_read' => false,
            'created_at' => now()
        ]);

        $messages = Message::where('chat_id', $this->chat->id)
            ->orderBy('created_at', 'asc')
            ->get();

        $this->assertEquals($message1->id, $messages[0]->id);
        $this->assertEquals($message2->id, $messages[1]->id);
        $this->assertEquals($message3->id, $messages[2]->id);
    }

    public function test_message_has_sender_type_field()
    {
        $message = Message::create([
            'chat_id' => $this->chat->id,
            'content' => 'Teste com sender_type',
            'sender_id' => $this->user->id,
            'sender_type' => 'user',
            'message_type' => 'text',
            'is_read' => false
        ]);
        $messageData = \Illuminate\Support\Facades\DB::table('messages')
            ->where('id', $message->id)
            ->first();

        $this->assertObjectHasProperty('sender_type', $messageData);
        $this->assertEquals('user', $messageData->sender_type);
    }

    public function test_can_access_metadata_as_array()
    {
        $metadata = [
            'file_url' => 'https://example.com/file.jpg',
            'file_size' => 1024,
            'mime_type' => 'image/jpeg'
        ];

        $message = Message::create([
            'chat_id' => $this->chat->id,
            'content' => 'Mensagem com metadados',
            'sender_id' => $this->user->id,
            'message_type' => 'image',
            'metadata' => $metadata,
            'is_read' => false
        ]);

        $this->assertIsArray($message->metadata);
        $this->assertEquals('https://example.com/file.jpg', $message->metadata['file_url']);
        $this->assertEquals(1024, $message->metadata['file_size']);
        $this->assertEquals('image/jpeg', $message->metadata['mime_type']);
    }
} 