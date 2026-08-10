<?php

namespace App\Notifications;

use App\Notifications\Contracts\CriticalNotification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Deliberately NOT ShouldQueue - see TenantSuspendedNotification for why.
 */
class TenantReactivatedNotification extends Notification implements CriticalNotification
{
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your workspace has been reactivated')
            ->greeting('Hello,')
            ->line('Your workspace has been reactivated and is accessible again.')
            ->action('Go to your workspace', url('/'));
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Workspace reactivated',
            'message' => 'Your workspace has been reactivated and is accessible again.',
            'action_url' => null,
        ];
    }
}
