<?php

namespace Tests\Feature\Notification;

use App\Jobs\Middleware\EstablishTenantConnection;
use App\Models\Admin;
use App\Models\Template;
use App\Models\User;
use App\Notifications\PasswordChangedNotification;
use App\Notifications\PasswordResetNotification;
use App\Notifications\WelcomeNotification;
use Illuminate\Support\Facades\Notification;
use Tests\TenantTestCase;

/**
 * Covers Sprint 0.10 (white-label tenant emails): the Templates module is
 * the actual source of these emails' subject/body, not hardcoded copy in
 * the Notification classes — a tenant edits the 'password_reset_email' (etc.)
 * template through the normal Templates UI and that's what gets sent, with
 * {company}/{prompt:...} placeholders resolved same as any other template.
 * Falls back to SystemEmailDefaults when a tenant has no row for a slot
 * (deleted it, or no tenant context at all).
 *
 * Also covers the bug this surfaced: PasswordChangedNotification's
 * 'database' channel write needs EstablishTenantConnection to land in the
 * right tenant's table when actually processed off a queue — these
 * notifications render subject/body BEFORE dispatch (RenderSystemTemplateUseCase,
 * called from the use case, not lazily inside toMail()) because
 * app('currentTenant') and the tenant's own template are both unreachable
 * from a worker with no HTTP request behind it.
 */
class WhiteLabelNotificationTest extends TenantTestCase
{
    private function provision(string $subdomain = 'acme', string $name = 'Acme Inc'): void
    {
        $this->artisan('tenant:provision', [
            'name' => $name,
            'subdomain' => $subdomain,
            '--admin-email' => "owner@{$subdomain}.test",
            '--admin-password' => 'super-secret',
        ])->assertExitCode(0);
    }

    public function test_provisioning_seeds_the_three_system_email_templates(): void
    {
        $this->provision();
        $this->useTenantHost('acme');

        $this->assertNotNull(Template::where('key', 'welcome_email')->first());
        $this->assertNotNull(Template::where('key', 'password_reset_email')->first());
        $this->assertNotNull(Template::where('key', 'password_changed_email')->first());
    }

    public function test_password_changed_notification_uses_the_seeded_templates_branding(): void
    {
        $this->provision();

        Notification::fake();

        $this->useTenantHost('acme');
        $token = $this->postJson('/api/admin/login', [
            'email' => 'owner@acme.test',
            'password' => 'super-secret',
        ])->assertStatus(200)->json('token');

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->patchJson('/api/admin/me/password', [
                'current_password' => 'super-secret',
                'password' => 'new-super-secret',
                'password_confirmation' => 'new-super-secret',
            ])->assertStatus(200);

        $admin = Admin::where('email', 'owner@acme.test')->first();

        Notification::assertSentTo($admin, PasswordChangedNotification::class, function (PasswordChangedNotification $notification) use ($admin) {
            $mail = $notification->toMail($admin);
            $this->assertSame([config('mail.from.address'), 'Acme Inc'], $mail->from);
            $this->assertSame('Your Acme Inc Password Was Changed', $mail->subject);

            $middleware = $notification->middleware($admin, 'database');
            $this->assertCount(1, $middleware);
            $this->assertInstanceOf(EstablishTenantConnection::class, $middleware[0]);

            return true;
        });
    }

    public function test_editing_the_tenants_template_changes_the_email_that_gets_sent(): void
    {
        // This is the actual point of Sprint 0.10: the template IS the
        // email, so editing it through the normal Templates CRUD changes
        // what a tenant's users receive - no separate "branding" config.
        $this->provision();
        $this->useTenantHost('acme');

        $template = Template::where('key', 'password_reset_email')->first();
        $template->update([
            'subject' => 'Custom subject for {company}',
            'body' => '<p>Custom body, link: {prompt:reset_url}</p>',
        ]);

        $user = User::create([
            'name' => 'Jean',
            'email' => 'jean@acme.test',
            'password' => 'super-secret',
            'email_verified_at' => now(),
        ]);

        Notification::fake();

        $this->postJson('/api/forgot-password', ['email' => 'jean@acme.test'])
            ->assertStatus(200);

        Notification::assertSentTo($user, PasswordResetNotification::class, function (PasswordResetNotification $notification) use ($user) {
            $mail = $notification->toMail($user);
            $this->assertSame('Custom subject for Acme Inc', $mail->subject);
            $this->assertStringContainsString('Custom body, link:', $mail->viewData['html']);
            $this->assertStringContainsString('/auth/reset-password?token=', $mail->viewData['html']);

            return true;
        });
    }

    public function test_deactivating_the_template_falls_back_to_the_default_copy(): void
    {
        $this->provision();
        $this->useTenantHost('acme');

        Template::where('key', 'password_reset_email')->first()->update(['is_active' => false]);

        $user = User::create([
            'name' => 'Jean',
            'email' => 'jean@acme.test',
            'password' => 'super-secret',
            'email_verified_at' => now(),
        ]);

        Notification::fake();

        $this->postJson('/api/forgot-password', ['email' => 'jean@acme.test'])
            ->assertStatus(200);

        Notification::assertSentTo($user, PasswordResetNotification::class, function (PasswordResetNotification $notification) use ($user) {
            $mail = $notification->toMail($user);
            $this->assertSame('Reset Your Acme Inc Password', $mail->subject);

            return true;
        });
    }

    public function test_notifications_fall_back_to_the_app_name_outside_any_tenant_context(): void
    {
        $notification = new WelcomeNotification('Welcome!', '<p>Hi</p>');
        $mail = $notification->toMail(new \stdClass());

        $this->assertSame(config('app.name'), $mail->from[1]);
        $this->assertSame([], $notification->middleware(new \stdClass(), 'mail'));
    }
}
