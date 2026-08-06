<?php

namespace Tests\Feature\Landlord;

use App\Application\UseCases\GodAdmin\SuspendTenantUseCase;
use App\Application\UseCases\Tenant\ProvisionTenantUseCase;
use App\Domain\Repositories\TenantRepositoryInterface;
use App\Models\Admin;
use App\Models\LandlordAuditLog;
use App\Notifications\TenantReactivatedNotification;
use App\Notifications\TenantSuspendedNotification;
use Illuminate\Support\Facades\Notification;
use Tests\TenantTestCase;

class TenantSuspensionNotificationTest extends TenantTestCase
{
    private const SYSTEM_ACTOR_ID = '00000000-0000-0000-0000-000000000000';

    public function test_suspending_a_tenant_notifies_its_owner(): void
    {
        Notification::fake();

        $tenant = app(ProvisionTenantUseCase::class)->execute(
            name: 'Notify Co',
            subdomain: 'notifyco',
            subscriptionPlanId: null,
            createdVia: 'godadmin',
            adminEmail: 'owner@notifyco.test',
            adminPassword: 'super-secret',
        );

        app(SuspendTenantUseCase::class)->execute(
            actorId: self::SYSTEM_ACTOR_ID,
            tenantId: $tenant->id,
            status: 'suspended',
        );

        $owner = Admin::where('email', 'owner@notifyco.test')->first();
        $this->assertNotNull($owner);

        Notification::assertSentTo($owner, TenantSuspendedNotification::class);
        Notification::assertNotSentTo($owner, TenantReactivatedNotification::class);
    }

    public function test_reactivating_a_tenant_notifies_its_owner(): void
    {
        Notification::fake();

        $tenant = app(ProvisionTenantUseCase::class)->execute(
            name: 'Reactivate Co',
            subdomain: 'reactivateco',
            subscriptionPlanId: null,
            createdVia: 'godadmin',
            adminEmail: 'owner@reactivateco.test',
            adminPassword: 'super-secret',
        );

        app(SuspendTenantUseCase::class)->execute(self::SYSTEM_ACTOR_ID, $tenant->id, 'suspended');
        app(SuspendTenantUseCase::class)->execute(self::SYSTEM_ACTOR_ID, $tenant->id, 'active');

        $owner = Admin::where('email', 'owner@reactivateco.test')->first();

        Notification::assertSentTo($owner, TenantSuspendedNotification::class);
        Notification::assertSentTo($owner, TenantReactivatedNotification::class);
    }

    public function test_suspending_a_tenant_with_no_owner_admin_does_not_error(): void
    {
        Notification::fake();

        $tenant = app(ProvisionTenantUseCase::class)->execute(
            name: 'Flagless Co',
            subdomain: 'flaglessco',
            subscriptionPlanId: null,
            createdVia: 'godadmin',
            adminEmail: 'owner@flaglessco.test',
            adminPassword: 'super-secret',
        );

        // Data edge case: no admin currently has is_tenant_owner set (e.g.
        // manually edited) — NotifyTenantOwnerUseCase's lookup should just
        // no-op, not error.
        Admin::where('email', 'owner@flaglessco.test')->update(['is_tenant_owner' => false]);

        $updated = app(SuspendTenantUseCase::class)->execute(self::SYSTEM_ACTOR_ID, $tenant->id, 'suspended');

        $this->assertSame('suspended', $updated->status);
        Notification::assertNothingSent();
    }

    public function test_suspension_succeeds_and_is_audited_even_if_the_owner_notification_fails(): void
    {
        // No migrations ever ran against this database — querying `admins`
        // inside NotifyTenantOwnerUseCase will throw.
        $tenant = app(TenantRepositoryInterface::class)->create(
            name: 'Bad DB Co',
            subdomain: 'baddb',
            databaseName: storage_path('app/sqlite/does-not-exist-'.uniqid().'.sqlite'),
            subscriptionPlanId: null,
            createdVia: 'godadmin',
            status: 'active',
        );

        $updated = app(SuspendTenantUseCase::class)->execute(self::SYSTEM_ACTOR_ID, $tenant->id, 'suspended');

        // The status change itself is unaffected by the notification failure.
        $this->assertSame('suspended', $updated->status);

        $failureLog = LandlordAuditLog::on('landlord')
            ->where('action', 'tenant_owner_notification_failed')
            ->where('model_id', $tenant->id)
            ->first();
        $this->assertNotNull($failureLog);

        // Connection state was restored afterward — a normal landlord query
        // still works correctly right after the failure.
        $reread = app(TenantRepositoryInterface::class)->findById($tenant->id);
        $this->assertSame('suspended', $reread->status);
    }
}
