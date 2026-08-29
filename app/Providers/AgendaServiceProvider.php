<?php

namespace App\Providers;

use App\Application\Agenda\Actions\AddToCalendarAction;
use App\Application\Agenda\Actions\ChangeStatusAction;
use App\Application\Agenda\Actions\OpenWhatsAppAction;
use App\Application\Agenda\Actions\RouteFromHereAction;
use App\Domain\Agenda\AppointmentActionRegistry;
use Illuminate\Support\ServiceProvider;

/**
 * The card's menu, assembled explicitly.
 *
 * Same stance as the agent tool registries: nothing becomes invocable by
 * existing in a folder. A vertical adds its own actions here — "generate
 * quote", "dispatch technician" — and the agenda itself never learns the word.
 */
class AgendaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AppointmentActionRegistry::class, function () {
            $registry = new AppointmentActionRegistry();

            $registry->register(new ChangeStatusAction());
            $registry->register(new OpenWhatsAppAction());
            $registry->register(new RouteFromHereAction());

            // Pre-filled "add event" URLs rather than an .ics download: the
            // provider's own compose screen opens already filled, so it is one
            // confirmation instead of a file, a download and an import dialog.
            $registry->register(new AddToCalendarAction(
                'google',
                'Google Calendar',
                'https://calendar.google.com/calendar/render?action=TEMPLATE'
                .'&text={title}&details={details}&location={location}'
                .'&dates={start_compact}/{end_compact}',
            ));

            $registry->register(new AddToCalendarAction(
                'outlook',
                'Outlook',
                'https://outlook.live.com/calendar/0/deeplink/compose?path=/calendar/action/compose'
                .'&subject={title}&body={details}&location={location}'
                .'&startdt={start_iso}&enddt={end_iso}',
            ));

            return $registry;
        });
    }
}
