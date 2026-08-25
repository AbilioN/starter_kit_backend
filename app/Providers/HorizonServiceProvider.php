<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Horizon::routeSmsNotificationsTo('15556667777');
        // Horizon::routeMailNotificationsTo('example@example.com');
        // Horizon's own long-wait/failed-job notifications, separate from the
        // health-check alerts (5.1.E) — these carry queue detail this app does
        // not model. Same webhook, so a team configuring alerting once gets
        // both.
        if (config('alerting.slack.enabled') && filled(config('alerting.slack.webhook'))) {
            Horizon::routeSlackNotificationsTo(config('alerting.slack.webhook'));
        }

        if (config('alerting.mail.enabled') && filled(config('alerting.mail.to'))) {
            // An array, not a spread: routeMailNotificationsTo() takes one
            // argument and hands it straight to Notification::route('mail', ...),
            // which accepts a list. Spreading would silently deliver to the
            // first address only.
            Horizon::routeMailNotificationsTo(array_map(
                'trim',
                explode(',', (string) config('alerting.mail.to')),
            ));
        }
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function ($user = null) {
            return in_array(optional($user)->email, [
                //
            ]);
        });
    }
}
