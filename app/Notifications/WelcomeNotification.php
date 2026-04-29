<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private string $name) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Welcome!')
            ->greeting("Hello, {$this->name}!")
            ->line('Your account has been created successfully.')
            ->action('Access the platform', url('/'))
            ->line('If you have any questions, feel free to reach out.');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Welcome!',
            'message' => "Hello, {$this->name}! Your account has been created successfully.",
            'action_url' => null,
        ];
    }
}
