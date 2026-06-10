<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class MessageRead implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $chatId,
        public readonly string $readerId,
        public readonly string $readerType,
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
