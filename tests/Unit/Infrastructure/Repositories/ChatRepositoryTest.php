<?php

namespace Tests\Unit\Infrastructure\Repositories;

use App\Infrastructure\Repositories\ChatRepository;
use App\Models\Admin;
use App\Models\Assistant;
use App\Models\Chat;
use Illuminate\Support\Facades\DB;
use Tests\TenantTestCase;

/**
 * Regression coverage for a bug where hasAssistant() queried
 * Chat::users() - a relation that (per its own docblock) always joins
 * against the User::class table regardless of the chat_user pivot's
 * user_type. An assistant participant's user_id never matches a real
 * users row, so it silently returned false for every assistant chat,
 * even a genuinely active one - which meant SendMessageToChatUseCase
 * never dispatched ProcessOpenAIRequest for any real assistant
 * conversation. The existing SendMessageToChatUseCaseTest suite never
 * caught this because it mocks ChatRepositoryInterface entirely; this
 * test exercises the real Eloquent query.
 */
class ChatRepositoryTest extends TenantTestCase
{
    public function test_has_assistant_is_true_for_a_chat_with_an_active_assistant_participant(): void
    {
        $this->actingAsTenant();

        $owner = Admin::factory()->create(['is_active' => true]);
        $chat = Chat::create(['type' => 'private', 'created_by' => $owner->id]);
        $assistant = Assistant::create(['name' => 'Support Bot', 'is_active' => true]);

        DB::table('chat_user')->insert([
            'chat_id' => $chat->id,
            'user_id' => $assistant->id,
            'user_type' => 'assistant',
            'is_active' => true,
            'joined_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertTrue((new ChatRepository())->hasAssistant($chat->id));
    }

    public function test_has_assistant_is_false_for_a_chat_between_two_regular_users(): void
    {
        $this->actingAsTenant();

        $owner = Admin::factory()->create(['is_active' => true]);
        $chat = Chat::create(['type' => 'private', 'created_by' => $owner->id]);

        DB::table('chat_user')->insert([
            'chat_id' => $chat->id,
            'user_id' => $owner->id,
            'user_type' => 'admin',
            'is_active' => true,
            'joined_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertFalse((new ChatRepository())->hasAssistant($chat->id));
    }

    public function test_has_assistant_is_false_when_the_assistant_participant_is_inactive(): void
    {
        $this->actingAsTenant();

        $owner = Admin::factory()->create(['is_active' => true]);
        $chat = Chat::create(['type' => 'private', 'created_by' => $owner->id]);
        $assistant = Assistant::create(['name' => 'Support Bot', 'is_active' => true]);

        DB::table('chat_user')->insert([
            'chat_id' => $chat->id,
            'user_id' => $assistant->id,
            'user_type' => 'assistant',
            'is_active' => false,
            'joined_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertFalse((new ChatRepository())->hasAssistant($chat->id));
    }
}
