<?php

namespace App\Events;

use App\Events\Concerns\ResolvesChatParticipantChannels;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageRead implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, ResolvesChatParticipantChannels, SerializesModels;

    public function __construct(
        public readonly string $chatId,
        public readonly string $readerId,
        public readonly string $readerType,
    ) {
        $this->participantChannels = $this->buildParticipantChannels($chatId);
    }

    public function broadcastOn(): array
    {
        return $this->chatChannels($this->chatId);
    }

    public function broadcastAs(): string
    {
        return 'MessageRead';
    }

    public function broadcastWith(): array
    {
        return [
            'chat_id'     => $this->chatId,
            'reader_id'   => $this->readerId,
            'reader_type' => $this->readerType,
            'read_at'     => now()->format('Y-m-d H:i:s'),
        ];
    }
}
