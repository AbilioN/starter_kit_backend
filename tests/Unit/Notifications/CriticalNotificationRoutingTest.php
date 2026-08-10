<?php

namespace Tests\Unit\Notifications;

use App\Models\Admin;
use App\Notifications\PasswordChangedNotification;
use App\Notifications\TenantReactivatedNotification;
use App\Notifications\TenantSuspendedNotification;
use PHPUnit\Framework\TestCase;

class CriticalNotificationRoutingTest extends TestCase
{
    private function admin(?string $notificationEmail): Admin
    {
        $admin = new Admin();
        $admin->email = 'login@acme.test';
        $admin->notification_email = $notificationEmail;

        return $admin;
    }

    public function test_critical_notifications_go_to_the_notification_email(): void
    {
        $admin = $this->admin('alertas@acme.test');

        $this->assertSame(
            'alertas@acme.test',
            $admin->routeNotificationForMail(new TenantSuspendedNotification()),
        );
        $this->assertSame(
            'alertas@acme.test',
            $admin->routeNotificationForMail(new TenantReactivatedNotification()),
        );
    }

    public function test_critical_notifications_fall_back_to_the_login_email_when_unset(): void
    {
        $admin = $this->admin(null);

        $this->assertSame(
            'login@acme.test',
            $admin->routeNotificationForMail(new TenantSuspendedNotification()),
        );
    }

    /**
     * A propriedade que torna seguro adiar a verificação do endereço: nada
     * relacionado a recuperação de conta é desviado para ele. Se este teste
     * for alterado para permitir o desvio, a verificação deixa de ser opcional.
     */
    public function test_account_recovery_mail_never_goes_to_the_notification_email(): void
    {
        $admin = $this->admin('alertas@acme.test');

        $notification = new PasswordChangedNotification('Assunto', '<p>corpo</p>');

        $this->assertSame(
            'login@acme.test',
            $admin->routeNotificationForMail($notification),
        );
    }
}
