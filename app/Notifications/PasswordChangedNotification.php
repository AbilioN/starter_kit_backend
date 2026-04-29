<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Password Changed')
            ->line('Your password was changed successfully.')
            ->line('If you did not make this change, please contact support immediately.');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Password Changed',
            'message' => 'Your password was changed successfully. If you did not make this change, contact support.',
            'action_url' => null,
        ];
    }
}
