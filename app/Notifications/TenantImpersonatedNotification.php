<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * Tells the tenant owner that a platform operator opened a support session in
 * their workspace.
 *
 * In-app only, by design. The audit entry is the permanent record and this is
 * the active heads-up; routing an ordinary support login to email as well
 * would turn every helpdesk ticket into a security alert, and alerts nobody
 * can act on are the ones people learn to ignore. (Roadmap 5.6 keeps the email
 * variant as an explicit, separately-decided option.)
 *
 * Deliberately NOT ShouldQueue — same reason as TenantSuspendedNotification:
 * it is dispatched while the `tenant` connection is pointed at this specific
 * tenant's database, and a queued worker would have no idea which database to
 * write the `database` channel row to.
 */
class TenantImpersonatedNotification extends Notification
{
    public function __construct(
        private string $operator,
        private string $impersonatedAdminName,
        private string $mode,
        private Carbon $expiresAt,
        private ?string $reason = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $access = $this->mode === 'write' ? 'read and write' : 'read-only';

        $message = sprintf(
            'Platform support (%s) opened a %s session as %s. It expires at %s.',
            $this->operator,
            $access,
            $this->impersonatedAdminName,
            $this->expiresAt->toDateTimeString(),
        );

        if ($this->reason) {
            $message .= ' Reason given: '.$this->reason;
        }

        return [
            'title' => 'Support access to your workspace',
            'message' => $message,
            'action_url' => '/audit',
        ];
    }
}
