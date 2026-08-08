<?php

namespace App\Notifications;

use App\Jobs\Middleware\EstablishTenantConnection;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * $subject/$htmlBody are pre-rendered by the caller — from the
     * tenant's own 'welcome_email' template (RenderSystemTemplateUseCase)
     * when one exists, falling back to a hardcoded default otherwise
     * (WelcomeNotificationDefaults). Rendering happens before dispatch, not
     * lazily in toMail(): this notification is queued, and neither
     * app('currentTenant') nor the tenant's template are reachable from a
     * worker with no HTTP request behind it. Same reasoning for
     * $tenantId/$tenantName (mail "from" branding) and $tenantId feeding
     * middleware() below.
     */
    public function __construct(
        private string $subject,
        private string $htmlBody,
        private ?string $tenantId = null,
        private ?string $tenantName = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $brand = $this->tenantName ?? config('app.name');

        return (new MailMessage)
            ->from(config('mail.from.address'), $brand)
            ->subject($this->subject)
            ->view('emails.rendered-template', ['html' => $this->htmlBody]);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->subject,
            'message' => strip_tags($this->htmlBody),
            'action_url' => null,
        ];
    }

    /**
     * Re-establishes the tenant connection on the worker before the
     * 'database' channel writes to the (tenant-scoped) notifications table
     * — without this, that write would land on whatever tenant connection
     * the worker last happened to have, not necessarily this one.
     */
    public function middleware(object $notifiable, string $channel): array
    {
        return $this->tenantId ? [new EstablishTenantConnection($this->tenantId)] : [];
    }
}
