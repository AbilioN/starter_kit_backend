<?php

namespace App\Events;

use App\Domain\Entities\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;

    /**
     * Create a new event instance.
     */
    public function __construct(Message $message)
    {
        $this->message = $message;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel
     */
    public function broadcastOn()
    {
        $channelName = 'chat.' . $this->message->chatId;
        Log::info('MessageSent event broadcasting on channel: ' . $channelName);
        return new Channel($channelName);
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'MessageSent';
    }

    /**
     * Determine if this event should broadcast.
     */
    public function broadcastWhen(): bool
    {
        // Sempre broadcast
        return true;
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith()
    {
        return [
            'id' => $this->message->id,
            'chat_id' => $this->message->chatId,
            'content' => $this->message->content,
            'sender_type' => $this->message->sender->getType(),
            'sender_id' => $this->message->sender->getId(),
            'is_read' => $this->message->isRead,
            'created_at' => $this->message->createdAt?->format('Y-m-d H:i:s') ?? now()->format('Y-m-d H:i:s')
        ];
    }
}
