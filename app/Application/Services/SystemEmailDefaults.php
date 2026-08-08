<?php

namespace App\Application\Services;

/**
 * Hardcoded fallback content for the system email slots (welcome_email,
 * password_reset_email, password_changed_email) — used when a tenant has
 * no template for a given key (they deleted it, or there's no tenant
 * context at all, e.g. tests/console). Mirrors SystemTemplateSeeder's own
 * default content so behavior doesn't visibly change whether the row
 * happens to exist or not; kept here (rather than only in the seeder) so
 * RenderSystemTemplateUseCase never has to touch the database to have
 * *something* to send.
 */
class SystemEmailDefaults
{
    private const DEFAULTS = [
        'welcome_email' => [
            'subject' => 'Welcome to {company}!',
            'body' => '<p>Hello, {prompt:name}!</p>'
                .'<p>Your account on {company} has been created successfully.</p>'
                .'<p><a href="{prompt:action_url}">Access the platform</a></p>'
                .'<p>If you have any questions, feel free to reach out.</p>',
        ],
        'password_reset_email' => [
            'subject' => 'Reset Your {company} Password',
            'body' => '<p>Hello!</p>'
                .'<p>You are receiving this email because we received a password reset request for your {company} account.</p>'
                .'<p><a href="{prompt:reset_url}">Reset Password</a></p>'
                .'<p>This password reset link will expire in 60 minutes.</p>'
                .'<p>If you did not request a password reset, no further action is required.</p>',
        ],
        'password_changed_email' => [
            'subject' => 'Your {company} Password Was Changed',
            'body' => '<p>Your {company} account password was changed successfully.</p>'
                .'<p>If you did not make this change, please contact support immediately.</p>',
        ],
    ];

    /**
     * @return array{subject: string, body: string}
     */
    public static function for(string $key): array
    {
        return self::DEFAULTS[$key] ?? ['subject' => '{company}', 'body' => ''];
    }
}
