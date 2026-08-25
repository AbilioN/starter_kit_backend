<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Destinations
    |--------------------------------------------------------------------------
    |
    | More than one may be enabled; each is tried independently, so a broken
    | Slack webhook does not cost the e-mail.
    |
    | E-mail is the default because it needs no workspace and works anywhere.
    | Slack is what people actually read. Neither is configured by default in
    | dev — an unconfigured destination is skipped and logged, never an error:
    | alerting must not be able to break the thing it is watching.
    |
    */

    'mail' => [
        'enabled' => env('ALERT_MAIL_ENABLED', true),
        // Comma-separated. Operators, not tenants — these alerts are about the
        // platform, and several of them name tenants.
        'to' => env('ALERT_MAIL_TO'),
    ],

    'slack' => [
        'enabled' => env('ALERT_SLACK_ENABLED', false),
        'webhook' => env('ALERT_SLACK_WEBHOOK'),
        // Short on purpose. This runs inside the health check; a hanging
        // webhook must not hold it open.
        'timeout_seconds' => (int) env('ALERT_SLACK_TIMEOUT', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Noise control
    |--------------------------------------------------------------------------
    |
    | The three settings that decide whether anyone is still reading alerts in
    | two weeks. An alerting system that cries every five minutes is worth
    | exactly as much as no alerting system, and takes longer to build.
    |
    */

    // Consecutive failing runs before the first alert. A single blip — one
    // slow Redis round trip, one restarting container — is not an incident,
    // and paging on it is how people learn to ignore the channel.
    'min_occurrences' => (int) env('ALERT_MIN_OCCURRENCES', 2),

    // How long before an unresolved problem is repeated. Long, deliberately:
    // the first alert already said it, and the reminder exists for the case
    // where the first one was missed, not to keep score.
    'repeat_after_minutes' => (int) env('ALERT_REPEAT_AFTER_MINUTES', 180),

    // Recovery notices. Worth their weight: without them, the only way to know
    // an incident ended is that the alerts stopped, which is indistinguishable
    // from the alerting itself having died.
    'notify_recovery' => env('ALERT_NOTIFY_RECOVERY', true),

    /*
    |--------------------------------------------------------------------------
    | Severity mapping
    |--------------------------------------------------------------------------
    |
    | Which readiness checks are worth a critical rather than a warning.
    | Mirrors CheckSystemHealthUseCase::CRITICAL — a `down` on anything else
    | still degrades the system without making it unusable.
    |
    */

    'critical_checks' => ['database', 'redis'],

    // State lives in the cache (Redis in every real deployment), keyed per
    // check. It has to outlive the CLI process — each `health:check --alert`
    // is a fresh PHP run with no memory of the last one.
    'state_prefix' => 'alerts:state:',
    'state_ttl_hours' => (int) env('ALERT_STATE_TTL_HOURS', 72),

];
