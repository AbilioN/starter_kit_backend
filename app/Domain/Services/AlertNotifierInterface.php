<?php

namespace App\Domain\Services;

use App\Domain\Entities\Alert;

/**
 * Delivers an alert somewhere a human will see it.
 *
 * The interface exists because the destination is a decision that changes with
 * the deployment, not with the code: e-mail needs nothing, Slack needs a
 * workspace, and a team that starts on one usually ends on the other. The
 * detection logic must not have to change when that does.
 *
 * **Implementations must never throw.** Alerting hangs off the health check;
 * an unreachable Slack webhook must not turn "the AI queue is slow" into "the
 * health check itself is broken", which is the failure mode where an operator
 * stops trusting the whole system.
 */
interface AlertNotifierInterface
{
    public function send(Alert $alert): void;
}
