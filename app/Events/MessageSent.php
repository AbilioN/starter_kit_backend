<?php

namespace App\Events;

use App\Domain\Entities\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;

    private array $participantChannels = [];

    public function __construct(Message $message)
    {
        $this->message = $message;
        $this->participantChannels = $this->buildParticipantChannels($message->chatId);
    }

    private function buildParticipantChannels(string $chatId): array
    {
        return DB::table('chat_user')
            ->where('chat_id', $chatId)
            ->where('is_active', true)
            ->whereIn('user_type', ['user', 'admin'])
            ->get(['user_id', 'user_type'])
            ->map(fn ($row) => new PrivateChannel("user.{$row->user_type}.{$row->user_id}"))
            ->all();
    }

    public function broadcastOn(): array
    {
        $channels = array_merge(
            [new PrivateChannel('chat.' . $this->message->chatId)],
            $this->participantChannels
        );

        Log::info('MessageSent broadcasting on channels', [
            'chat_id' => $this->message->chatId,
            'participant_channels' => count($this->participantChannels),
        ]);

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'MessageSent';
    }

    public function broadcastWhen(): bool
    {
        return true;
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'chat_id' => $this->message->chatId,
            'content' => $this->message->content,
            'sender_type' => $this->message->sender->getType(),
            'sender_id' => $this->message->sender->getId(),
            'is_read' => $this->message->isRead,
            'created_at' => $this->message->createdAt?->format('Y-m-d H:i:s') ?? now()->format('Y-m-d H:i:s'),
        ];
    }
}
