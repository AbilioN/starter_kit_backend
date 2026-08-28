<?php

namespace App\Events;

use App\Events\Concerns\ResolvesChatParticipantChannels;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageEdited implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, ResolvesChatParticipantChannels, SerializesModels;

    public function __construct(
        public readonly string $messageId,
        public readonly string $chatId,
        public readonly string $newContent,
        public readonly string $editedAt,
    ) {
        $this->participantChannels = $this->buildParticipantChannels($chatId);
    }

    public function broadcastOn(): array
    {
        return $this->chatChannels($this->chatId);
    }

    public function broadcastAs(): string
    {
        return 'MessageEdited';
    }

    public function broadcastWith(): array
    {
        return [
            'id'          => $this->messageId,
            'chat_id'     => $this->chatId,
            'content'     => $this->newContent,
            'edited_at'   => $this->editedAt,
        ];
    }
}
