<?php

namespace Tests\Unit\Domain\Entities;

use App\Domain\Entities\ChatUserFactory;
use App\Models\Assistant as AssistantModel;
use Tests\TenantTestCase;

/**
 * Regression coverage for a bug where createAssistantFromModel() passed
 * the nullable `capabilities` column straight into Assistant's non-nullable
 * `array` constructor param. Every Assistant row created via
 * SyncAgentProfilesForTenantUseCase has a null capabilities column (that
 * use case never sets it), so any chat with a profile-synced assistant
 * crashed the moment a ChatUser was built from it (e.g. create-private,
 * send-message). Needs TenantTestCase because Assistant is a tenant model.
 */
class ChatUserFactoryTest extends TenantTestCase
{
    public function test_creates_an_assistant_chat_user_when_capabilities_column_is_null(): void
    {
        $this->actingAsTenant();

        $model = AssistantModel::create([
            'name' => 'Support Bot',
            'description' => 'Synced from an agent profile',
            'is_active' => true,
        ]);
        $this->assertNull($model->capabilities);

        $chatUser = ChatUserFactory::createAssistantFromModel($model);

        $this->assertSame($model->id, $chatUser->getId());
        $this->assertSame('assistant', $chatUser->getType());
    }
}
