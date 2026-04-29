<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // General
            ['key' => 'app.name', 'value' => 'Starter Kit', 'type' => 'string', 'group' => 'general', 'label' => 'Application Name', 'description' => 'The name shown across the platform.', 'is_public' => true],
            ['key' => 'app.timezone', 'value' => 'UTC', 'type' => 'string', 'group' => 'general', 'label' => 'Timezone', 'description' => 'Default timezone for dates and times.', 'is_public' => true],

            // Features (feature flags)
            ['key' => 'features.chat', 'value' => 'true', 'type' => 'boolean', 'group' => 'features', 'label' => 'Chat Module', 'description' => 'Enable or disable the real-time chat module.', 'is_public' => false],
            ['key' => 'features.file_upload', 'value' => 'true', 'type' => 'boolean', 'group' => 'features', 'label' => 'File Upload', 'description' => 'Enable or disable file uploads.', 'is_public' => false],
            ['key' => 'features.notifications', 'value' => 'true', 'type' => 'boolean', 'group' => 'features', 'label' => 'Notifications', 'description' => 'Enable or disable in-app notifications.', 'is_public' => false],
            ['key' => 'features.ai_agent', 'value' => 'false', 'type' => 'boolean', 'group' => 'features', 'label' => 'AI Agent', 'description' => 'Enable or disable AI agent integration.', 'is_public' => false],

            // Email
            ['key' => 'email.from_name', 'value' => 'Starter Kit', 'type' => 'string', 'group' => 'email', 'label' => 'Email Sender Name', 'description' => 'Name shown in the From field of emails.', 'is_public' => false],
            ['key' => 'email.from_address', 'value' => 'hello@example.com', 'type' => 'string', 'group' => 'email', 'label' => 'Email Sender Address', 'description' => 'Address used in the From field of emails.', 'is_public' => false],
            ['key' => 'email.welcome_enabled', 'value' => 'true', 'type' => 'boolean', 'group' => 'email', 'label' => 'Welcome Email', 'description' => 'Send a welcome email when a user registers.', 'is_public' => false],

            // Storage
            ['key' => 'storage.default_disk', 'value' => 'local', 'type' => 'string', 'group' => 'storage', 'label' => 'Default Storage Disk', 'description' => 'Default disk for file uploads (local or s3).', 'is_public' => false],
            ['key' => 'storage.max_upload_mb', 'value' => '100', 'type' => 'integer', 'group' => 'storage', 'label' => 'Max Upload Size (MB)', 'description' => 'Maximum file size in megabytes for uploads.', 'is_public' => true],
            ['key' => 'storage.allowed_mimes', 'value' => '["image/jpeg","image/png","image/gif","image/webp","application/pdf","text/plain","text/csv"]', 'type' => 'array', 'group' => 'storage', 'label' => 'Allowed MIME Types', 'description' => 'Allowed file types for upload.', 'is_public' => true],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(['key' => $setting['key']], $setting);
        }
    }
}
