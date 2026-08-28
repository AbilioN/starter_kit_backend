<?php

namespace App\Events;

use App\Domain\Entities\Message;
use App\Events\Concerns\ResolvesChatParticipantChannels;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, ResolvesChatParticipantChannels, SerializesModels;

    public $message;

    public function __construct(Message $message)
    {
        $this->message = $message;
        $this->participantChannels = $this->buildParticipantChannels($message->chatId);
    }

    public function broadcastOn(): array
    {
        $channels = $this->chatChannels($this->message->chatId);

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
