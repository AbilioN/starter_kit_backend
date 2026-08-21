<?php

namespace Tests\Feature\GodAdmin;

use App\Application\UseCases\GodAdmin\StartImpersonationUseCase;
use App\Models\Admin;
use App\Models\GodAdmin;
use App\Models\Tenant;
use App\Notifications\TenantImpersonatedNotification;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TenantTestCase;

class ImpersonationTest extends TenantTestCase
{
    private GodAdmin $godAdmin;

    private Tenant $tenant;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->actingAsTenant();

        $this->godAdmin = GodAdmin::create([
            'name' => 'Root',
            'email' => 'root@starterkit.test',
            'password' => 'secret-password',
        ]);

        $this->admin = Admin::create([
            'name' => 'Tenant Owner',
            'email' => 'owner@tenant.test',
            'password' => bcrypt('password123'),
            'is_active' => true,
            'is_super_admin' => true,
            'is_tenant_owner' => true,
        ]);

        // HasAuditLog resolves Auth::user() on every model event; leaving the
        // godadmin guard as the default would make it resolve to a GodAdmin on
        // writes that are not impersonated. Same workaround as
        // TenantManagementTest.
        Auth::shouldUse('web');
    }

    private function start(string $mode = StartImpersonationUseCase::MODE_READ, ?string $reason = 'Ticket #1234'): array
    {
        return app(StartImpersonationUseCase::class)->execute(
            godAdminId: (string) $this->godAdmin->id,
            tenantId: (string) $this->tenant->id,
            adminId: (string) $this->admin->id,
            mode: $mode,
            reason: $reason,
        );
    }

    public function test_a_session_issues_a_token_bound_to_the_operator(): void
    {
        $session = $this->start();

        $token = PersonalAccessToken::findToken($session['token']);

        $this->assertNotNull($token);
        // The operator, from the token's own column — the only attribution
        // that cannot be influenced by the client.
        $this->assertSame((string) $this->godAdmin->id, $token->impersonated_by);
        $this->assertSame((string) $this->admin->id, (string) $token->tokenable_id);
    }

    public function test_the_token_expires_on_its_own(): void
    {
        $session = $this->start();

        $token = PersonalAccessToken::findToken($session['token']);

        $this->assertNotNull($token->expires_at);
        $this->assertEqualsWithDelta(
            StartImpersonationUseCase::SESSION_MINUTES,
            now()->diffInMinutes($token->expires_at),
            1,
        );
    }

    public function test_no_password_is_involved(): void
    {
        // The whole point: a customer who signed up online has a password
        // nobody at the platform ever knew.
        $original = $this->admin->fresh()->password;

        $this->start();

        $this->assertSame($original, $this->admin->fresh()->password);
    }

    public function test_a_default_session_can_read(): void
    {
        $session = $this->start();

        $this->withToken($session['token'])
            ->getJson('/api/admin/me')
            ->assertOk();
    }

    public function test_a_default_session_cannot_write(): void
    {
        $session = $this->start();

        $this->withToken($session['token'])
            ->patchJson('/api/admin/me', ['name' => 'Renamed by support'])
            ->assertForbidden()
            ->assertJsonPath('error', 'impersonation_read_only');

        $this->assertSame('Tenant Owner', $this->admin->fresh()->name);
    }

    public function test_a_write_session_can_write(): void
    {
        $session = $this->start(StartImpersonationUseCase::MODE_WRITE);

        $this->withToken($session['token'])
            ->patchJson('/api/admin/me', ['name' => 'Renamed by support'])
            ->assertOk();

        $this->assertSame('Renamed by support', $this->admin->fresh()->name);
    }

    public function test_an_ordinary_admin_session_is_unaffected(): void
    {
        // The guard runs on every api request; the regression that matters is
        // it silently blocking normal admins.
        $token = $this->admin->createToken('admin-api')->plainTextToken;

        $this->withToken($token)
            ->patchJson('/api/admin/me', ['name' => 'Renamed by the owner'])
            ->assertOk();

        $this->withToken($token)
            ->getJson('/api/admin/impersonation')
            ->assertOk()
            ->assertJsonPath('data.active', false);
    }

    public function test_it_refuses_an_inactive_admin(): void
    {
        $this->admin->update(['is_active' => false]);

        $this->expectException(DomainException::class);

        $this->start();
    }

    public function test_it_refuses_a_suspended_tenant(): void
    {
        // A token minted for a suspended tenant would be rejected by
        // IdentifyTenant on arrival anyway.
        $this->tenant->update(['status' => 'suspended']);

        $this->expectException(DomainException::class);

        $this->start();
    }

    public function test_the_access_lands_in_the_tenants_own_audit_log(): void
    {
        $this->start();

        $entry = DB::connection('tenant')->table('audit_logs')
            ->where('action', 'impersonation_started')
            ->first();

        $this->assertNotNull($entry, 'the tenant cannot see an access that was never written to their audit log');
        // Attributed to the operator, never to the admin whose account was used.
        $this->assertSame('GodAdmin', $entry->user_type);
        $this->assertSame((string) $this->godAdmin->id, $entry->user_id);
        $this->assertStringContainsString('Ticket #1234', $entry->metadata);
    }

    public function test_the_access_lands_in_the_landlord_audit_log(): void
    {
        $this->start();

        $entry = DB::connection('landlord')->table('landlord_audit_logs')
            ->where('action', 'impersonation_started')
            ->first();

        $this->assertNotNull($entry);
        $this->assertSame((string) $this->godAdmin->id, $entry->actor_id);
        $this->assertSame((string) $this->tenant->id, $entry->model_id);
    }

    public function test_the_tenant_owner_is_notified(): void
    {
        Notification::fake();

        $this->start();

        Notification::assertSentTo($this->admin, TenantImpersonatedNotification::class);
    }

    public function test_the_owner_notification_is_actually_stored(): void
    {
        // Notification::fake() above proves the notification was *dispatched*.
        // It was, and it still never arrived: notifications.notifiable_id was a
        // bigint while every notifiable has a UUID, so MySQL rejected the write
        // and Laravel swallowed it. This asserts delivery instead.
        //
        // Honest limitation: the suite runs on SQLite, which is loosely typed
        // and accepted the same insert happily. This test would not have caught
        // the original bug — only checking the real database did. It does guard
        // against the notification silently not being written at all.
        $this->start();

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => Admin::class,
            'notifiable_id' => (string) $this->admin->id,
            'type' => TenantImpersonatedNotification::class,
        ], 'tenant');
    }

    public function test_a_write_made_during_a_session_names_the_operator(): void
    {
        // The property that makes the audit log trustworthy: it must never
        // report that the tenant's own admin did something an operator did.
        $session = $this->start(StartImpersonationUseCase::MODE_WRITE);

        $this->withToken($session['token'])
            ->patchJson('/api/admin/me', ['name' => 'Renamed by support'])
            ->assertOk();

        $entry = DB::connection('tenant')->table('audit_logs')
            ->where('action', 'updated')
            ->orderByDesc('created_at')
            ->first();

        $this->assertNotNull($entry);
        $this->assertStringContainsString((string) $this->godAdmin->id, (string) $entry->metadata);
        $this->assertStringContainsString('impersonation', (string) $entry->tags);
    }

    public function test_the_panel_can_describe_the_running_session(): void
    {
        $session = $this->start();

        $this->withToken($session['token'])
            ->getJson('/api/admin/impersonation')
            ->assertOk()
            ->assertJsonPath('data.active', true)
            ->assertJsonPath('data.can_write', false)
            ->assertJsonPath('data.operator', 'root@starterkit.test');
    }

    public function test_a_read_only_session_can_still_end_itself(): void
    {
        // Ending is a POST. Without the explicit exemption the guard would
        // trap the operator inside the session until it expired.
        $session = $this->start();

        $this->withToken($session['token'])
            ->postJson('/api/admin/impersonation/stop')
            ->assertOk();
    }

    public function test_realtime_still_works_in_a_read_only_session(): void
    {
        // broadcasting/auth is a POST that authorizes a channel subscription.
        // Blocking it kills every realtime feature — and "the chat does not
        // update" is exactly the kind of report support is asked to reproduce.
        // A 403 here would mean the operator sees a working chat that is
        // silently dead, which is worse than not looking at all.
        $session = $this->start();

        $response = $this->withToken($session['token'])
            ->postJson('/api/broadcasting/auth', ['channel_name' => 'private-user.admin.'.$this->admin->id, 'socket_id' => '1234.5678']);

        // Whether Pusher itself authorizes the channel is beside the point;
        // what matters is that the guard did not refuse it.
        $this->assertNotSame(403, $response->status(), 'the guard blocked the realtime handshake');
    }

    public function test_ending_a_session_revokes_the_token(): void
    {
        $session = $this->start();

        $this->withToken($session['token'])->postJson('/api/admin/impersonation/stop')->assertOk();

        $this->assertNull(PersonalAccessToken::findToken($session['token']));

        // The guard instance survives between requests inside a test (one
        // application, several requests), so it would answer from the user it
        // resolved a moment ago instead of re-checking the token. Real
        // requests each get a fresh process; this restores that condition.
        $this->app['auth']->forgetGuards();

        $this->withToken($session['token'])
            ->getJson('/api/admin/me')
            ->assertUnauthorized();
    }

    public function test_ending_a_session_is_recorded_on_both_sides(): void
    {
        $session = $this->start();

        $this->withToken($session['token'])->postJson('/api/admin/impersonation/stop')->assertOk();

        $this->assertDatabaseHas('audit_logs', ['action' => 'impersonation_ended'], 'tenant');
        $this->assertDatabaseHas('landlord_audit_logs', ['action' => 'impersonation_ended'], 'landlord');
    }
}
