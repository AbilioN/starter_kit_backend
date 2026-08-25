<?php

namespace App\Notifications;

use App\Domain\Entities\Alert;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The e-mail form of an alert.
 *
 * Deliberately NOT queued. Every other notification in this app is, but an
 * alert about the queue being broken cannot be delivered by the queue — that is
 * the one message guaranteed to be stuck exactly when it matters.
 */
class SystemAlertNotification extends Notification
{
    use Queueable;

    public function __construct(private Alert $alert) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->alert->subject())
            ->greeting($this->alert->title)
            ->line($this->alert->message);

        foreach ($this->alert->context as $key => $value) {
            $mail->line('**'.$key.'**: '.(is_array($value) ? implode(', ', $value) : var_export($value, true)));
        }

        return $this->alert->isRecovery()
            ? $mail->line('No further action needed — this is the all-clear.')
            : $mail->line('Run `php artisan health:check` for the full picture.');
    }
}
