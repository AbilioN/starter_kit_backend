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
        // What an appointment is ABOUT. An allow-list, registered by hand for
        // the same reason the action registry is: a morph column holds a
        // string somebody wrote, and resolving it blindly would instantiate
        // whatever it names.
        $this->app->singleton(
            \App\Application\Services\AppointmentSubjectResolver::class,
            fn () => \App\Application\Services\AppointmentSubjectResolver::withDefaults(),
        );

        // NO global Relation::morphMap here, and that is deliberate.
        //
        // Registering `'user' => User::class` looked harmless — it would let
        // `$appointment->subject` resolve the short key. But a morph map is
        // not read-only: `Model::getMorphClass()` consults it, so User would
        // start WRITING 'user' into every polymorphic column it touches.
        // `notifications.notifiable_type` is one of them, and four call sites
        // filter that column on the literal 'App\Models\User' — so every user
        // notification created afterwards would be invisible in the list, the
        // unread count, mark-all-read and the agent tool, while older rows
        // stayed readable. `personal_access_tokens.tokenable_type` flips too.
        //
        // `enforceMorphMap` vs `morphMap` changes only the READ side; both
        // flip the write. The suite cannot catch it either, because the
        // notification tests use Notification::fake() and no row is written.
        //
        // AppointmentSubjectResolver carries its own allow-list and needs no
        // global map. `$appointment->subject` therefore still does not resolve
        // the short key — it did not before this change either, and the
        // resolver is the intended path.

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
