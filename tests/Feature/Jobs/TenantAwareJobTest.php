<?php

namespace Tests\Feature\Jobs;

use App\Domain\Entities\ChatUserFactory;
use App\Jobs\ProcessMessageJob;
use App\Models\Admin;
use App\Models\Chat;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Tests\TenantTestCase;

/**
 * Proves EstablishTenantConnection actually re-establishes tenant context
 * on a queue worker, regardless of whatever database.default was left
 * pointing at before the job ran - not just that the job runs without
 * error. QUEUE_CONNECTION=sync (phpunit.xml default) so this dispatches
 * for real rather than via Queue::fake(), which wouldn't exercise the
 * connection-switching at all.
 */
class TenantAwareJobTest extends TenantTestCase
{
    private Tenant $tenantA;

    private Tenant $tenantB;

    private string $chatId;

    private string $ownerAId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('tenant:provision', [
            'name' => 'Tenant A',
            'subdomain' => 'tenant-a',
            '--admin-email' => 'owner@tenant-a.test',
            '--admin-password' => 'password-a',
        ])->assertExitCode(0);
        $this->tenantA = Tenant::on('landlord')->where('subdomain', 'tenant-a')->first();

        $this->artisan('tenant:provision', [
            'name' => 'Tenant B',
            'subdomain' => 'tenant-b',
            '--admin-email' => 'owner@tenant-b.test',
            '--admin-password' => 'password-b',
        ])->assertExitCode(0);
        $this->tenantB = Tenant::on('landlord')->where('subdomain', 'tenant-b')->first();

        // Set up a chat + participant on tenant A's own database.
        $this->pointTenantConnectionAt($this->tenantA);
        $owner = Admin::where('email', 'owner@tenant-a.test')->first();
        $this->ownerAId = $owner->id;
        $chat = Chat::create(['type' => 'group', 'name' => 'Test Chat', 'created_by' => $owner->id]);
        $chat->addParticipant(ChatUserFactory::createFromModel($owner));
        $this->chatId = $chat->id;
    }

    public function test_job_writes_to_the_tenant_it_was_dispatched_for_not_whatever_default_currently_is(): void
    {
        // Deliberately leave database.default pointed at tenant B - the
        // WRONG tenant - right before dispatching. If EstablishTenantConnection
        // works, the job corrects this using the tenantId it was given.
        $this->pointTenantConnectionAt($this->tenantB);

        ProcessMessageJob::dispatch(
            $this->chatId,
            $this->ownerAId,
            'admin',
            'Hello from the queue',
            'text',
            null,
            'message_processing',
            null,
            $this->tenantA->id,
        );

        $this->pointTenantConnectionAt($this->tenantA);
        $this->assertDatabaseHas('messages', [
            'chat_id' => $this->chatId,
            'content' => 'Hello from the queue',
        ], 'tenant');

        $this->pointTenantConnectionAt($this->tenantB);
        $this->assertDatabaseMissing('messages', [
            'chat_id' => $this->chatId,
        ], 'tenant');
    }

    private function pointTenantConnectionAt(Tenant $tenant): void
    {
        config(['database.connections.tenant.database' => $tenant->database_name]);
        config(['database.default' => 'tenant']);
        DB::purge('tenant');
    }
}
