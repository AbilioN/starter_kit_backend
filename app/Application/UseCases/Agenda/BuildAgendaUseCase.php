<?php

namespace App\Application\UseCases\Agenda;

use App\Domain\Agenda\AppointmentActionRegistry;
use App\Models\Appointment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Builds a whole agenda screen: the window, the grid, the counts and the cards.
 *
 * Two shapes are taken straight from the MADCRM study.
 *
 * **One loader, one flat list.** Every dated thing is read once into a single
 * ordered collection, and every view — day, week, month — is a projection of
 * that same list. Four views, one query path. There it needed a UNION per date
 * column to achieve this; here the appointments table gives it for free, which
 * is most of the reason for the table.
 *
 * **The server computes the grid; the client renders it.** Every call returns
 * the whole screen state rather than a diff, including counts per axis. It
 * costs bandwidth and buys the absence of a class of bug where the client and
 * the server disagree about what is on screen.
 */
class BuildAgendaUseCase
{
    /** The hours a working day is drawn between; outside is clamped into the edges. */
    private const DAY_START_HOUR = 7;
    private const DAY_END_HOUR = 20;

    public function __construct(private AppointmentActionRegistry $actions) {}

    /**
     * @param  callable(?string): bool  $allows  whether the viewer may use an
     *         action requiring a given slug
     */
    public function execute(
        string $view,
        CarbonImmutable $date,
        array $filters,
        ?string $groupBy,
        callable $allows,
    ): array {
        [$from, $to] = $this->window($view, $date);

        $appointments = $this->load($from, $to, $filters);

        $groups = $groupBy
            ? $this->group($appointments, $groupBy)
            : ['' => $appointments];

        return [
            'view' => $view,
            'date' => $date->toDateString(),
            'from' => $from->toDateTimeString(),
            'to' => $to->toDateTimeString(),
            'iso_week' => (int) $date->isoWeek(),
            'group_by' => $groupBy,
            'filters' => $filters,
            'totals' => $this->counts($appointments),
            'groups' => collect($groups)->map(fn (Collection $rows, string $key) => [
                // Never a blank heading: an unlabelled bucket reads as a bug.
                'key' => $key === '' ? null : $key,
                'label' => $key === '' ? null : ($key === '__none__' ? '[Unassigned]' : $key),
                'totals' => $this->counts($rows),
                ...$this->project($view, $from, $to, $rows, $allows),
            ])->values()->all(),
        ];
    }

    /**
     * Window boundaries per view. Week starts on Monday; month is the whole
     * month regardless of which day was asked for.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function window(string $view, CarbonImmutable $date): array
    {
        return match ($view) {
            'day' => [$date->startOfDay(), $date->endOfDay()],
            'month' => [$date->startOfMonth()->startOfDay(), $date->endOfMonth()->endOfDay()],
            default => [$date->startOfWeek()->startOfDay(), $date->endOfWeek()->endOfDay()],
        };
    }

    private function load(CarbonImmutable $from, CarbonImmutable $to, array $filters): Collection
    {
        return Appointment::query()
            ->with(['type', 'status'])
            ->overlapping($from, $to)
            ->when($filters['type_id'] ?? null, fn ($q, $v) => $q->where('appointment_type_id', $v))
            ->when($filters['status_id'] ?? null, fn ($q, $v) => $q->where('appointment_status_id', $v))
            ->when($filters['assigned_admin_id'] ?? null, fn ($q, $v) => $q->where('assigned_admin_id', $v))
            ->orderBy('starts_at')
            ->get();
    }

    /**
     * Counts sit next to every axis — per day, per hour, per group. The agenda
     * doubles as the daily dashboard, which is why "12 appointments, 8
     * confirmed" belongs in the payload rather than being recomputed by whoever
     * draws it.
     */
    private function counts(Collection $appointments): array
    {
        return [
            'count' => $appointments->count(),
            'confirmed' => $appointments->filter(
                fn (Appointment $a) => (bool) ($a->status?->counts_as_confirmed)
            )->count(),
        ];
    }

    private function group(Collection $appointments, string $groupBy): array
    {
        $key = match ($groupBy) {
            'assigned_admin' => fn (Appointment $a) => $a->assigned_admin_id ?? '__none__',
            'type' => fn (Appointment $a) => $a->type?->label ?? '__none__',
            'status' => fn (Appointment $a) => $a->status?->label ?? '__none__',
            'city' => fn (Appointment $a) => $a->location_city ?? '__none__',
            default => fn (Appointment $a) => '',
        };

        return $appointments->groupBy($key)->all();
    }

    /** Day view is columns of hours; week and month are columns of days. */
    private function project(
        string $view,
        CarbonImmutable $from,
        CarbonImmutable $to,
        Collection $rows,
        callable $allows,
    ): array {
        return $view === 'day'
            ? ['hours' => $this->hours($from, $rows, $allows)]
            : ['days' => $this->days($view, $from, $to, $rows, $allows)];
    }

    private function days(string $view, CarbonImmutable $from, CarbonImmutable $to, Collection $rows, callable $allows): array
    {
        $days = [];

        for ($day = $from; $day->lessThan($to); $day = $day->addDay()) {
            $dayStart = $day->startOfDay();
            $dayEnd = $day->endOfDay();

            // Overlap again, not equality on the start date: a two-day
            // appointment belongs to both of its days.
            $onThisDay = $rows->filter(fn (Appointment $a) =>
                $a->starts_at < $dayEnd && $a->ends_at > $dayStart);

            $days[] = [
                'date' => $day->toDateString(),
                'is_today' => $day->isToday(),
                ...$this->counts($onThisDay),
                // A month view carries counts only. It is the cheapest of the
                // views and the one people navigate with; rendering every card
                // of a month is a wall nobody reads.
                'appointments' => $view === 'month'
                    ? null
                    : $onThisDay->map(fn (Appointment $a) => $this->card($a, $allows))->values()->all(),
            ];
        }

        return $days;
    }

    private function hours(CarbonImmutable $day, Collection $rows, callable $allows): array
    {
        $hours = [];

        for ($hour = self::DAY_START_HOUR; $hour <= self::DAY_END_HOUR; $hour++) {
            // Everything before the first hour and after the last is clamped
            // into the edge buckets rather than dropped: an 06:00 appointment
            // must still be visible on the day it belongs to.
            $inBucket = $rows->filter(function (Appointment $a) use ($hour) {
                $startHour = (int) $a->starts_at->format('G');
                $clamped = max(self::DAY_START_HOUR, min(self::DAY_END_HOUR, $startHour));

                return $clamped === $hour;
            });

            $hours[] = [
                'hour' => $hour,
                'label' => match ($hour) {
                    self::DAY_START_HOUR => '≤ '.self::DAY_START_HOUR.'h',
                    self::DAY_END_HOUR => '≥ '.self::DAY_END_HOUR.'h',
                    default => $hour.'h',
                },
                ...$this->counts($inBucket),
                'appointments' => $inBucket->map(fn (Appointment $a) => $this->card($a, $allows))->values()->all(),
            ];
        }

        return $hours;
    }

    /**
     * The card is a small dossier plus its menu — built once, on the server, so
     * the client has nothing to assemble and no rule to re-implement.
     */
    private function card(Appointment $appointment, callable $allows): array
    {
        return [
            'id' => $appointment->id,
            'title' => $appointment->title,
            'description' => $appointment->description,
            'starts_at' => $appointment->starts_at->toIso8601String(),
            'ends_at' => $appointment->ends_at->toIso8601String(),
            'all_day' => $appointment->all_day,
            'spans_days' => ! $appointment->starts_at->isSameDay($appointment->ends_at),
            'type' => $appointment->type ? [
                'slug' => $appointment->type->slug,
                'label' => $appointment->type->label,
                'color' => $appointment->type->color,
                'icon' => $appointment->type->icon,
            ] : null,
            'status' => $appointment->status ? [
                'slug' => $appointment->status->slug,
                'label' => $appointment->status->label,
                'color' => $appointment->status->color,
                'confirmed' => $appointment->status->counts_as_confirmed,
            ] : null,
            'assigned_admin_id' => $appointment->assigned_admin_id,
            'location' => [
                'address' => $appointment->location_address,
                'postcode' => $appointment->location_postcode,
                'city' => $appointment->location_city,
                'lat' => $appointment->location_lat,
                'lng' => $appointment->location_lng,
                'geocoded' => $appointment->location_lat !== null,
            ],
            'subject' => $appointment->subject_type ? [
                'type' => $appointment->subject_type,
                'id' => $appointment->subject_id,
            ] : null,
            'actions' => $this->actions->menuFor($appointment, $allows),
        ];
    }
}
