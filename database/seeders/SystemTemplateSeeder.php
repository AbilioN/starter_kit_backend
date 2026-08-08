<?php

namespace Database\Seeders;

use App\Models\Template;
use Illuminate\Database\Seeder;

/**
 * Seeds the well-known system-email template slots (see RenderSystemTemplateUseCase
 * and the `key` column's docblock in its migration) every tenant needs so
 * WelcomeNotification/PasswordResetNotification/PasswordChangedNotification
 * always have something to render. Content here is just a starting point —
 * a tenant customizes it afterward through the normal Templates UI, editing
 * body/subject like any other template. {company} resolves to the tenant's
 * own name automatically (PlaceholderResolverService); {prompt:...} values
 * are supplied fresh by the caller on each send (recipient name, a
 * one-time reset URL — never persisted).
 *
 * Idempotent (updateOrCreate by key) so it's safe to run again on an
 * already-seeded tenant — used both at provisioning (ProvisionTenantUseCase)
 * and to backfill tenants provisioned before this slot existed
 * (`tenant:seed-system-templates`).
 */
class SystemTemplateSeeder extends Seeder
{
    public function run(): void
    {
        Template::updateOrCreate(['key' => 'welcome_email'], [
            'name' => 'Welcome Email',
            'type' => 'html_email',
            'body_format' => 'html',
            'subject' => 'Welcome to {company}!',
            'description' => 'Sent when a new account is created.',
            'is_active' => true,
            'body' => <<<'HTML'
                <p>Hello, {prompt:name}!</p>
                <p>Your account on {company} has been created successfully.</p>
                <p><a href="{prompt:action_url}">Access the platform</a></p>
                <p>If you have any questions, feel free to reach out.</p>
                HTML,
        ]);

        Template::updateOrCreate(['key' => 'password_reset_email'], [
            'name' => 'Password Reset Email',
            'type' => 'html_email',
            'body_format' => 'html',
            'subject' => 'Reset Your {company} Password',
            'description' => 'Sent when a password reset is requested.',
            'is_active' => true,
            'body' => <<<'HTML'
                <p>Hello!</p>
                <p>You are receiving this email because we received a password reset request for your {company} account.</p>
                <p><a href="{prompt:reset_url}">Reset Password</a></p>
                <p>This password reset link will expire in 60 minutes.</p>
                <p>If you did not request a password reset, no further action is required.</p>
                HTML,
        ]);

        Template::updateOrCreate(['key' => 'password_changed_email'], [
            'name' => 'Password Changed Email',
            'type' => 'html_email',
            'body_format' => 'html',
            'subject' => 'Your {company} Password Was Changed',
            'description' => 'Sent after a password change.',
            'is_active' => true,
            'body' => <<<'HTML'
                <p>Your {company} account password was changed successfully.</p>
                <p>If you did not make this change, please contact support immediately.</p>
                HTML,
        ]);
    }
}
