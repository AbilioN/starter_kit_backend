<?php

namespace App\Notifications;

use App\Notifications\Contracts\CriticalNotification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Deliberately NOT ShouldQueue - dispatched synchronously from
 * NotifyTenantOwnerUseCase while the `tenant` connection is temporarily
 * pointed at this specific tenant's database. A queued dispatch would run
 * on a worker with no idea which tenant database to write the `database`
 * channel entry to (the same class of bug EstablishTenantConnection exists
 * to prevent for other queued jobs).
 */
class TenantSuspendedNotification extends Notification implements CriticalNotification
{
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your workspace has been suspended')
            ->greeting('Hello,')
            ->line('Your workspace has been suspended by the platform administrator.')
            ->line('You will not be able to access it until it is reactivated.')
            ->line('If you believe this is a mistake, please contact support.');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Workspace suspended',
            'message' => 'Your workspace has been suspended by the platform administrator. Contact support if you believe this is a mistake.',
            'action_url' => null,
        ];
    }
}
