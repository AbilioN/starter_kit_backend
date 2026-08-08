<?php

namespace App\Notifications;

use App\Jobs\Middleware\EstablishTenantConnection;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordResetNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * $subject/$htmlBody are pre-rendered by the caller — see
     * WelcomeNotification's docblock for why this can't happen lazily
     * inside toMail() on a queue worker.
     */
    public function __construct(
        private string $subject,
        private string $htmlBody,
        private ?string $tenantId = null,
        private ?string $tenantName = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $brand = $this->tenantName ?? config('app.name');

        return (new MailMessage)
            ->from(config('mail.from.address'), $brand)
            ->subject($this->subject)
            ->view('emails.rendered-template', ['html' => $this->htmlBody]);
    }

    public function middleware(object $notifiable, string $channel): array
    {
        return $this->tenantId ? [new EstablishTenantConnection($this->tenantId)] : [];
    }
}
