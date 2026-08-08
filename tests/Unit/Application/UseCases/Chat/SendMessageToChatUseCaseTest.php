<?php

namespace Tests\Unit\Application\UseCases\Chat;

use App\Application\UseCases\Chat\SendMessageToChatUseCase;
use App\Application\UseCases\Setting\GetSettingByKeyUseCase;
use App\Domain\Entities\ChatUser;
use App\Domain\Entities\Message;
use App\Domain\Repositories\ChatRepositoryInterface;
use App\Domain\Repositories\MessageRepositoryInterface;
use App\Events\MessageSent;
use App\Jobs\ProcessOpenAIRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class SendMessageToChatUseCaseTest extends TestCase
{
    private SendMessageToChatUseCase $useCase;
    private ChatRepositoryInterface $chatRepository;
    private MessageRepositoryInterface $messageRepository;
    private GetSettingByKeyUseCase $getSettingByKey;
    private ChatUser $sender;
    private Message $message;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake([ProcessOpenAIRequest::class]);
        // Prevents a real broadcast (Pusher) attempt once MessageSent is
        // dispatched. Doesn't help with the line below though — `new
        // static(...)` inside Dispatchable::dispatch() constructs the
        // event (and runs its constructor's DB query) before handing off
        // to the (faked) dispatcher.
        Event::fake([MessageSent::class]);

        // MessageSent's constructor queries chat_user directly via the DB
        // facade to build participant channels — mock it out rather than
        // hitting a real DB, same pattern as GetChatMessagesUseCaseTest.
        DB::shouldReceive('table')
            ->with('chat_user')
            ->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('whereIn')->andReturnSelf();
        DB::shouldReceive('get')->andReturn(collect());

        $this->chatRepository = Mockery::mock(ChatRepositoryInterface::class);
        $this->messageRepository = Mockery::mock(MessageRepositoryInterface::class);
        $this->getSettingByKey = Mockery::mock(GetSettingByKeyUseCase::class);

        $this->useCase = new SendMessageToChatUseCase(
            $this->messageRepository,
            $this->chatRepository,
            $this->getSettingByKey,
        );

        $this->sender = Mockery::mock(ChatUser::class);
        $this->sender->shouldReceive('getId')->andReturn('user-1');
        $this->sender->shouldReceive('getType')->andReturn('user');
        $this->sender->shouldReceive('getName')->andReturn('Test User');

        // A real value object rather than a mock — Message's properties are
        // readonly, which Mockery::mock() (extends without running the
        // constructor) can't populate safely.
        $this->message = new Message(
            id: 'message-1',
            chatId: 'chat-1',
            content: 'hello',
            sender: $this->sender,
            messageType: 'text',
            metadata: null,
            isRead: false,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_does_not_dispatch_openai_request_when_ai_agent_feature_disabled()
    {
        $this->messageRepository
            ->shouldReceive('create')
            ->once()
            ->andReturn($this->message);

        $this->chatRepository
            ->shouldReceive('hasAssistant')
            ->with('chat-1')
            ->andReturn(true);

        $this->getSettingByKey
            ->shouldReceive('execute')
            ->with('features.ai_agent')
            ->once()
            ->andReturn(['value' => false]);

        $this->useCase->execute('chat-1', 'hello', $this->sender);

        Queue::assertNotPushed(ProcessOpenAIRequest::class);
    }

    public function test_does_not_dispatch_openai_request_when_ai_agent_setting_missing()
    {
        $this->messageRepository
            ->shouldReceive('create')
            ->once()
            ->andReturn($this->message);

        $this->chatRepository
            ->shouldReceive('hasAssistant')
            ->with('chat-1')
            ->andReturn(true);

        // No setting row for this tenant yet (e.g. not seeded) — must default
        // to disabled, not throw or silently enable.
        $this->getSettingByKey
            ->shouldReceive('execute')
            ->with('features.ai_agent')
            ->once()
            ->andReturn(null);

        $this->useCase->execute('chat-1', 'hello', $this->sender);

        Queue::assertNotPushed(ProcessOpenAIRequest::class);
    }

    public function test_dispatches_openai_request_when_ai_agent_feature_enabled()
    {
        $this->messageRepository
            ->shouldReceive('create')
            ->once()
            ->andReturn($this->message);

        $this->chatRepository
            ->shouldReceive('hasAssistant')
            ->with('chat-1')
            ->andReturn(true);

        $this->getSettingByKey
            ->shouldReceive('execute')
            ->with('features.ai_agent')
            ->once()
            ->andReturn(['value' => true]);

        $this->useCase->execute('chat-1', 'hello', $this->sender);

        Queue::assertPushed(ProcessOpenAIRequest::class);
    }

    public function test_does_not_check_ai_agent_feature_when_chat_has_no_assistant()
    {
        $this->messageRepository
            ->shouldReceive('create')
            ->once()
            ->andReturn($this->message);

        $this->chatRepository
            ->shouldReceive('hasAssistant')
            ->with('chat-1')
            ->andReturn(false);

        $this->getSettingByKey->shouldNotReceive('execute');

        $this->useCase->execute('chat-1', 'hello', $this->sender);

        Queue::assertNotPushed(ProcessOpenAIRequest::class);
    }

    public function test_does_not_dispatch_when_sender_is_the_assistant_itself()
    {
        $assistantSender = Mockery::mock(ChatUser::class);
        $assistantSender->shouldReceive('getId')->andReturn('assistant-1');
        $assistantSender->shouldReceive('getType')->andReturn('assistant');
        $assistantSender->shouldReceive('getName')->andReturn('AI Assistant');

        $this->messageRepository
            ->shouldReceive('create')
            ->once()
            ->andReturn($this->message);

        $this->chatRepository
            ->shouldReceive('hasAssistant')
            ->with('chat-1')
            ->andReturn(true);

        $this->getSettingByKey->shouldNotReceive('execute');

        $this->useCase->execute('chat-1', 'hello', $assistantSender);

        Queue::assertNotPushed(ProcessOpenAIRequest::class);
    }
}
