<?php

namespace Tests\Unit\Application\UseCases\Chat;

use App\Application\DTOs\ChatMessagesResponseDto;
use App\Application\DTOs\PaginationDto;
use App\Application\UseCases\Chat\GetChatMessagesUseCase;
use App\Domain\Entities\ChatUser;
use App\Domain\Repositories\ChatRepositoryInterface;
use App\Domain\Repositories\MessageRepositoryInterface;
use Tests\TestCase;
use Mockery;
use Illuminate\Support\Facades\DB;

class GetChatMessagesUseCaseTest extends TestCase
{
    private GetChatMessagesUseCase $useCase;
    private ChatRepositoryInterface $chatRepository;
    private MessageRepositoryInterface $messageRepository;
    private ChatUser $chatUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->chatRepository = Mockery::mock(ChatRepositoryInterface::class);
        $this->messageRepository = Mockery::mock(MessageRepositoryInterface::class);
        $this->useCase = new GetChatMessagesUseCase(
            $this->chatRepository,
            $this->messageRepository
        );
        
        $this->chatUser = Mockery::mock(ChatUser::class);
        $this->chatUser->shouldReceive('getId')->andReturn(1);
        $this->chatUser->shouldReceive('getType')->andReturn('user');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_execute_returns_chat_messages_response_when_user_is_participant()
    {
        // Arrange
        $chatId = 1;
        $page = 1;
        $perPage = 50;
        
        // Mock DB query for chat_user table
        DB::shouldReceive('table')
            ->with('chat_user')
            ->andReturnSelf()
            ->shouldReceive('where')
            ->with('chat_id', $chatId)
            ->andReturnSelf()
            ->shouldReceive('where')
            ->with('user_id', 1)
            ->andReturnSelf()
            ->shouldReceive('value')
            ->with('user_type')
            ->andReturn('user');
        
        $messages = [
            [
                'id' => 1,
                'chat_id' => $chatId,
                'content' => 'Test message',
                'sender_id' => 1,
                'sender_type' => 'user',
                'message_type' => 'text',
                'metadata' => null,
                'is_read' => false,
                'read_at' => null,
                'created_at' => '2024-01-01 00:00:00',
                'updated_at' => '2024-01-01 00:00:00'
            ]
        ];
        
        $pagination = [
            'current_page' => 1,
            'per_page' => 50,
            'total' => 1,
            'last_page' => 1,
            'from' => 1,
            'to' => 1
        ];

        $this->chatRepository
            ->shouldReceive('hasParticipant')
            ->with($chatId, $this->chatUser)
            ->once()
            ->andReturn(true);

        $this->messageRepository
            ->shouldReceive('getChatMessages')
            ->with($chatId, $page, $perPage)
            ->once()
            ->andReturn([
                'messages' => $messages,
                'pagination' => $pagination
            ]);

        // Act
        $result = $this->useCase->execute($this->chatUser, $chatId, $page, $perPage);

        // Assert
        $this->assertInstanceOf(ChatMessagesResponseDto::class, $result);
        $this->assertFalse($result->fromCache);
        $this->assertInstanceOf(PaginationDto::class, $result->pagination);
        $this->assertEquals(1, $result->pagination->currentPage);
        $this->assertEquals(50, $result->pagination->perPage);
        $this->assertEquals(1, $result->pagination->total);
    }

    public function test_execute_throws_exception_when_user_is_not_participant()
    {
        // Arrange
        $chatId = 1;
        $page = 1;
        $perPage = 50;

        $this->chatRepository
            ->shouldReceive('hasParticipant')
            ->with($chatId, $this->chatUser)
            ->once()
            ->andReturn(false);

        // Act & Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Access denied');
        $this->expectExceptionCode(403);

        $this->useCase->execute($this->chatUser, $chatId, $page, $perPage);
    }

    public function test_execute_enriches_messages_with_sender_type()
    {
        // Arrange
        $chatId = 1;
        $page = 1;
        $perPage = 50;
        
        // Mock DB query for chat_user table
        DB::shouldReceive('table')
            ->with('chat_user')
            ->andReturnSelf()
            ->shouldReceive('where')
            ->with('chat_id', $chatId)
            ->andReturnSelf()
            ->shouldReceive('where')
            ->with('user_id', 1)
            ->andReturnSelf()
            ->shouldReceive('value')
            ->with('user_type')
            ->andReturn('user');
        
        $messages = [
            [
                'id' => 1,
                'chat_id' => $chatId,
                'content' => 'Test message',
                'sender_id' => 1,
                'message_type' => 'text',
                'metadata' => null,
                'is_read' => false,
                'read_at' => null,
                'created_at' => '2024-01-01 00:00:00',
                'updated_at' => '2024-01-01 00:00:00'
            ]
        ];
        
        $pagination = [
            'current_page' => 1,
            'per_page' => 50,
            'total' => 1,
            'last_page' => 1,
            'from' => 1,
            'to' => 1
        ];

        $this->chatRepository
            ->shouldReceive('hasParticipant')
            ->with($chatId, $this->chatUser)
            ->once()
            ->andReturn(true);

        $this->messageRepository
            ->shouldReceive('getChatMessages')
            ->with($chatId, $page, $perPage)
            ->once()
            ->andReturn([
                'messages' => $messages,
                'pagination' => $pagination
            ]);

        // Act
        $result = $this->useCase->execute($this->chatUser, $chatId, $page, $perPage);

        // Assert
        $this->assertInstanceOf(ChatMessagesResponseDto::class, $result);
        $this->assertCount(1, $result->messages);
        $this->assertEquals('user', $result->messages[0]['sender_type']);
    }
}
