<?php

namespace App\Events\Concerns;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Facades\DB;

/**
 * Resolves the private channels of a chat's participants.
 *
 * The one rule here: call this from the event's CONSTRUCTOR and keep the
 * result, never from broadcastOn(). broadcastOn() runs inside the queued
 * broadcast job, and under database-per-tenant the tenant connection
 * established by the request is gone by then — the query would run against the
 * landlord database and die on a missing chat_user table, so the event is
 * simply never delivered. MessageRead, MessageEdited and MessageDeleted each
 * had their own copy of this query in broadcastOn(); all three were silently
 * failing for that reason.
 *
 * The array is a serialized property of the event, so the channels are
 * whoever was in the chat when it happened — which is what the receipt or the
 * edit actually describes.
 */
trait ResolvesChatParticipantChannels
{
    /** @var array<int, PrivateChannel> */
    private array $participantChannels = [];

    /** @return array<int, PrivateChannel> */
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

    /**
     * The chat's own channel plus one per participant, in the order every
     * chat event broadcasts them.
     *
     * @return array<int, PrivateChannel>
     */
    private function chatChannels(string $chatId): array
    {
        return array_merge(
            [new PrivateChannel('chat.' . $chatId)],
            $this->participantChannels
        );
    }
}
