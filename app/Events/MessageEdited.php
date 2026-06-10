<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class MessageEdited implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $messageId,
        public readonly string $chatId,
        public readonly string $newContent,
        public readonly string $editedAt,
    ) {}

    public function broadcastOn(): array
    {
        $participantChannels = DB::table('chat_user')
            ->where('chat_id', $this->chatId)
            ->where('is_active', true)
            ->whereIn('user_type', ['user', 'admin'])
            ->get(['user_id', 'user_type'])
            ->map(fn($row) => new PrivateChannel("user.{$row->user_type}.{$row->user_id}"))
            ->all();

        return array_merge(
            [new PrivateChannel('chat.' . $this->chatId)],
            $participantChannels
        );
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
