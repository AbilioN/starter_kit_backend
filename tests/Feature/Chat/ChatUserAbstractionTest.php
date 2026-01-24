<?php

namespace Tests\Feature\Chat;

use App\Domain\Entities\Admin;
use App\Domain\Entities\ChatUser;
use App\Domain\Entities\ChatUserFactory;
use App\Domain\Entities\User;
use App\Models\Admin as AdminModel;
use App\Models\Chat;
use App\Models\Message;
use App\Models\User as UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatUserAbstractionTest extends TestCase
{
    use RefreshDatabase;

    protected $userModel;
    protected $adminModel;
    protected $user;
    protected $admin;
    protected $chat;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Criar User primeiro (ID será 1)
        $this->userModel = UserModel::factory()->create();
        
        // Criar Admin com ID específico usando insert direto
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
        
        $this->adminModel = AdminModel::find(2);
        
        // Cria entidades de domínio
        $this->user = ChatUserFactory::createUserFromModel($this->userModel);
        $this->admin = ChatUserFactory::createAdminFromModel($this->adminModel);
        
        $this->chat = Chat::create([
            'name' => 'Chat Teste',
            'type' => 'private',
            'created_by' => $this->userModel->id
        ]);
    }

    public function test_can_create_chat_user_from_models()
    {
        // Cria ChatUsers a partir dos modelos
        $userChatUser = ChatUserFactory::createUserFromModel($this->userModel);
        $adminChatUser = ChatUserFactory::createAdminFromModel($this->adminModel);

        // Verifica que são instâncias de ChatUser
        $this->assertInstanceOf(ChatUser::class, $userChatUser);
        $this->assertInstanceOf(ChatUser::class, $adminChatUser);

        // Verifica que são instâncias das entidades corretas
        $this->assertInstanceOf(User::class, $userChatUser);
        $this->assertInstanceOf(Admin::class, $adminChatUser);

        // Verifica os tipos
        $this->assertEquals('user', $userChatUser->getType());
        $this->assertEquals('admin', $adminChatUser->getType());

        // Verifica os IDs
        $this->assertEquals($this->userModel->id, $userChatUser->getId());
        $this->assertEquals($this->adminModel->id, $adminChatUser->getId());

        // Verifica os nomes
        $this->assertEquals($this->userModel->name, $userChatUser->getName());
        $this->assertEquals($this->adminModel->name, $adminChatUser->getName());
    }

    public function test_can_create_chat_user_from_model_generic()
    {
        // Cria ChatUsers usando o método genérico
        $userChatUser = ChatUserFactory::createFromModel($this->userModel);
        $adminChatUser = ChatUserFactory::createFromModel($this->adminModel);

        // Verifica que são instâncias corretas
        $this->assertInstanceOf(ChatUser::class, $userChatUser);
        $this->assertInstanceOf(ChatUser::class, $adminChatUser);

        // Verifica que são instâncias das entidades corretas
        $this->assertInstanceOf(User::class, $userChatUser);
        $this->assertInstanceOf(Admin::class, $adminChatUser);

        // Verifica os tipos
        $this->assertEquals('user', $userChatUser->getType());
        $this->assertEquals('admin', $adminChatUser->getType());
    }

    public function test_can_add_chat_users_to_chat()
    {
        // Adiciona participantes ao chat
        $this->chat->addParticipant($this->user);
        $this->chat->addParticipant($this->admin);

        // Verifica se foram adicionados
        $this->assertTrue($this->chat->hasParticipant($this->user));
        $this->assertTrue($this->chat->hasParticipant($this->admin));

        // Verifica se estão na tabela chat_user
        $participants = \Illuminate\Support\Facades\DB::table('chat_user')
            ->where('chat_id', $this->chat->id)
            ->get();

        $this->assertCount(2, $participants);

        // Verifica os tipos
        $userParticipant = $participants->where('user_type', 'user')->first();
        $adminParticipant = $participants->where('user_type', 'admin')->first();

        $this->assertNotNull($userParticipant);
        $this->assertNotNull($adminParticipant);
    }

    public function test_can_get_active_chat_users()
    {
        // Adiciona participantes ao chat
        $this->chat->addParticipant($this->user);
        $this->chat->addParticipant($this->admin);

        // Obtém participantes ativos
        $activeChatUsers = $this->chat->getActiveChatUsers();

        $this->assertCount(2, $activeChatUsers);

        // Verifica que são instâncias de ChatUser
        foreach ($activeChatUsers as $chatUser) {
            $this->assertInstanceOf(ChatUser::class, $chatUser);
        }

        // Verifica os tipos
        $userTypes = array_map(fn($cu) => $cu->getType(), $activeChatUsers);
        $this->assertContains('user', $userTypes);
        $this->assertContains('admin', $userTypes);
    }

    public function test_can_create_private_chat_between_chat_users()
    {
        // Cria chat privado entre eles
        $chat = Chat::findOrCreatePrivateChat($this->user, $this->admin);

        // Verifica que o chat foi criado
        $this->assertNotNull($chat);
        $this->assertEquals('private', $chat->type);

        // Verifica que ambos são participantes
        $this->assertTrue($chat->hasParticipant($this->user));
        $this->assertTrue($chat->hasParticipant($this->admin));

        // Verifica que o nome do chat é o nome do segundo usuário
        $this->assertEquals($this->admin->getName(), $chat->name);
    }

    public function test_can_send_message_and_get_sender_chat_user()
    {
        // Adiciona participantes ao chat
        $this->chat->addParticipant($this->user);
        $this->chat->addParticipant($this->admin);

        // Cria mensagem
        $message = Message::create([
            'chat_id' => $this->chat->id,
            'content' => 'Olá, mundo!',
            'sender_id' => $this->user->getId(),
            'sender_type' => $this->user->getType(),
            'message_type' => 'text',
            'is_read' => false
        ]);

        // Obtém o ChatUser que enviou a mensagem
        $senderChatUser = $message->getSenderChatUser();

        // Verifica que é o usuário correto
        $this->assertNotNull($senderChatUser);
        $this->assertInstanceOf(ChatUser::class, $senderChatUser);
        $this->assertEquals($this->user->getId(), $senderChatUser->getId());
        $this->assertEquals($this->user->getType(), $senderChatUser->getType());
        $this->assertEquals($this->user->getName(), $senderChatUser->getName());
    }

    public function test_can_query_messages_by_chat_user()
    {
        // Adiciona participantes ao chat
        $this->chat->addParticipant($this->user);
        $this->chat->addParticipant($this->admin);

        // Cria mensagens
        Message::create([
            'chat_id' => $this->chat->id,
            'content' => 'Mensagem do usuário',
            'sender_id' => $this->user->getId(),
            'sender_type' => $this->user->getType(),
            'message_type' => 'text',
            'is_read' => false
        ]);

        Message::create([
            'chat_id' => $this->chat->id,
            'content' => 'Mensagem do admin',
            'sender_id' => $this->admin->getId(),
            'sender_type' => $this->admin->getType(),
            'message_type' => 'text',
            'is_read' => false
        ]);

        // Busca mensagens por ChatUser
        $userMessages = Message::bySender($this->user)->get();
        $adminMessages = Message::bySender($this->admin)->get();

        $this->assertCount(1, $userMessages);
        $this->assertCount(1, $adminMessages);

        $this->assertEquals('Mensagem do usuário', $userMessages->first()->content);
        $this->assertEquals('Mensagem do admin', $adminMessages->first()->content);
    }

    public function test_can_mark_messages_as_read_for_chat_user()
    {
        // Adiciona participantes ao chat
        $this->chat->addParticipant($this->user);
        $this->chat->addParticipant($this->admin);

        // Cria mensagens não lidas
        Message::create([
            'chat_id' => $this->chat->id,
            'content' => 'Mensagem não lida 1',
            'sender_id' => $this->admin->getId(),
            'sender_type' => $this->admin->getType(),
            'message_type' => 'text',
            'is_read' => false
        ]);

        Message::create([
            'chat_id' => $this->chat->id,
            'content' => 'Mensagem não lida 2',
            'sender_id' => $this->admin->getId(),
            'sender_type' => $this->admin->getType(),
            'message_type' => 'text',
            'is_read' => false
        ]);

        // Marca mensagens como lidas para o usuário
        $this->chat->markAsReadForChatUser($this->user);

        // Verifica se as mensagens foram marcadas como lidas
        $unreadMessages = $this->chat->messages()->where('is_read', false)->get();
        $this->assertCount(0, $unreadMessages);
    }

    public function test_domain_entities_implement_chat_user_interface()
    {
        // Verifica que as entidades de domínio implementam ChatUser
        $this->assertInstanceOf(ChatUser::class, $this->user);
        $this->assertInstanceOf(ChatUser::class, $this->admin);

        // Verifica que são instâncias das entidades corretas
        $this->assertInstanceOf(User::class, $this->user);
        $this->assertInstanceOf(Admin::class, $this->admin);

        // Testa métodos da interface
        $this->assertEquals($this->userModel->id, $this->user->getId());
        $this->assertEquals($this->userModel->name, $this->user->getName());
        $this->assertEquals($this->userModel->email, $this->user->getEmail());
        $this->assertEquals('user', $this->user->getType());

        $this->assertEquals($this->adminModel->id, $this->admin->getId());
        $this->assertEquals($this->adminModel->name, $this->admin->getName());
        $this->assertEquals($this->adminModel->email, $this->admin->getEmail());
        $this->assertEquals('admin', $this->admin->getType());
    }
} 