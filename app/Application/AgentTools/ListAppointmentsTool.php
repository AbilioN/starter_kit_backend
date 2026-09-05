<?php

namespace App\Application\AgentTools;

use App\Application\CustomFields\FieldViewerFactory;
use App\Application\UseCases\CustomField\ProjectCustomFieldsUseCase;
use App\Domain\AgentTools\AgentToolContext;
use App\Domain\AgentTools\AgentToolInterface;
use App\Domain\AgentTools\AgentToolResult;
use App\Domain\AgentTools\Exceptions\AgentToolFailure;
use App\Models\Admin;
use App\Models\Appointment;
use Carbon\CarbonImmutable;

/**
 * What is scheduled, between two dates.
 *
 * The business core was invisible to the assistant: 108 API routes, and the
 * only thing it could say about this workspace was how many users it had. A
 * venue owner asking "what have we got on Saturday?" got nothing. This is the
 * tool that changes that, and it is deliberately the first of the widening
 * because the agenda is what the product is *for*.
 *
 * ## Dates, not the panel's vocabulary
 *
 * `BuildAgendaUseCase` takes a `view` (day/week/month) plus an anchor date,
 * which is right for a screen with three tabs and wrong for a model, which
 * already knows that "Saturday" is a date. So this takes `from`/`to` and calls
 * the same `overlapping()` scope the agenda does — the one compound index on
 * this table exists for exactly that predicate.
 *
 * It does not reuse `BuildAgendaUseCase` itself, for two reasons that pull the
 * same way: that use case also builds a per-card action menu and grouping,
 * both of which a sentence throws away, and its `custom` payload is
 * deliberately narrowed to the `card.badges` slot — right for a scan-many
 * surface, wrong here, because a field a tenant chose to keep off the card is
 * still a fact about the appointment and the model should be able to read it.
 *
 * ## Masking
 *
 * Custom values go through `ProjectCustomFieldsUseCase` with a viewer built
 * from the GRANT's actor, never `Auth::` — the same rule as
 * `ListCustomFieldsTool`, for the same reason: on this path there is no
 * authenticated user, and `FieldViewerFactory::forAdmin(null)` returns
 * `FieldViewer::system()`, which bypasses every rule. The executor already
 * refuses a grant whose actor cannot be resolved, because this tool declares a
 * permission; the explicit check below is the belt to that braces, since the
 * guard lives in another class and depends on `permission()` staying non-null.
 */
final class ListAppointmentsTool implements AgentToolInterface
{
    /**
     * A model asked for "this year" would otherwise pull twelve months to fill
     * a result cap that truncates it anyway — the cost is paid in the query,
     * not in the rows returned.
     */
    private const MAX_SPAN_DAYS = 62;

    public function __construct(
        private ProjectCustomFieldsUseCase $customFields,
        private FieldViewerFactory $viewers,
    ) {}

    public function name(): string
    {
        return 'list_appointments';
    }

    public function description(): string
    {
        return 'List what is scheduled in this workspace between two dates — the title, when it '
            .'starts and ends, its type and status, who it is assigned to, where it is, and any '
            .'custom fields this workspace tracks on it. Use this for any question about the '
            .'agenda, bookings, availability or what is happening on a given day. Dates are '
            .'inclusive and in YYYY-MM-DD.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'from' => [
                    'type' => 'string',
                    'description' => 'First day to include, YYYY-MM-DD. Defaults to today.',
                ],
                'to' => [
                    'type' => 'string',
                    'description' => 'Last day to include, YYYY-MM-DD. Defaults to the same day as `from`.',
                ],
            ],
            'additionalProperties' => false,
        ];
    }

    public function permission(): ?string
    {
        return 'appointment-read';
    }

    public function isMutating(): bool
    {
        return false;
    }

    public function execute(array $arguments, AgentToolContext $context): AgentToolResult
    {
        $actor = $context->actorType === 'admin' ? Admin::find($context->actorId) : null;

        if ($actor === null) {
            // Fail CLOSED. forAdmin(null) hands back the system viewer, which
            // bypasses every hide rule.
            throw AgentToolFailure::permissionDenied($this->permission());
        }

        $viewer = $this->viewers->forAdmin($actor);

        [$from, $to] = $this->window($arguments);

        $appointments = Appointment::query()
            ->with(['type', 'status', 'assignedAdmin'])
            ->overlapping($from, $to)
            ->orderBy('starts_at')
            ->limit($context->maxRows + 1)
            ->get();

        // One call for the labels, then one per row for the values — the
        // context is per response, not per record.
        $labels = [];

        foreach ($this->customFields->context('appointments', $viewer) as $field) {
            $labels[$field['key']] = $field['label'];
        }

        $rows = $appointments->map(function (Appointment $appointment) use ($viewer, $labels) {
            $row = [
                'id' => $appointment->id,
                'title' => $appointment->title,
                'starts_at' => $appointment->starts_at->toIso8601String(),
                'ends_at' => $appointment->ends_at->toIso8601String(),
                'all_day' => (bool) $appointment->all_day,
                'type' => $appointment->type?->label,
                'status' => $appointment->status?->label,
                // The tenant's own word for "this one is going ahead". The
                // agenda branches on it, so an answer about availability that
                // ignored it would be wrong in the one case that matters.
                'confirmed' => $appointment->status?->counts_as_confirmed,
                'assigned_to' => $appointment->assignedAdmin?->name,
                'where' => $this->where($appointment),
            ];

            // Nested under `custom`, NOT merged into the row.
            //
            // The label is a tenant-authored string and the row has reserved
            // keys: a workspace that names a field "title" or "status" would
            // otherwise overwrite the real one, and a field named "confirmed"
            // could flip an availability answer. Keyed by label rather than by
            // `cf_7` because the label is the only form the model — or the
            // person reading its answer — can do anything with.
            $custom = [];

            foreach ($this->customFields->values('appointments', $appointment, $viewer) as $value) {
                $label = $labels[$value['key']] ?? null;

                if ($label !== null && ($value['text'] ?? null) !== null && $value['text'] !== '') {
                    $custom[$label] = $value['text'];
                }
            }

            if ($custom !== []) {
                $row['custom'] = $custom;
            }

            return array_filter($row, fn ($v) => $v !== null && $v !== '');
        })->all();

        return AgentToolResult::rows($rows, $context->maxRows);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function window(array $arguments): array
    {
        try {
            // The tenant's own timezone, matching the `Today is …` line the
            // system prompt carries. Reading one and defaulting to the other
            // would tell the model it is Saturday and then answer for Friday.
            $timezone = (string) (\App\Helpers\Settings::get('app.timezone') ?: config('app.timezone', 'UTC'));

            $from = isset($arguments['from'])
                ? CarbonImmutable::parse($arguments['from'], $timezone)->startOfDay()
                : CarbonImmutable::now($timezone)->startOfDay();

            $to = isset($arguments['to'])
                ? CarbonImmutable::parse($arguments['to'], $timezone)->endOfDay()
                : $from->endOfDay();
        } catch (\Throwable) {
            throw AgentToolFailure::validation('Dates must be in YYYY-MM-DD form.');
        }

        if ($to->lessThan($from)) {
            throw AgentToolFailure::validation('`to` cannot be before `from`.');
        }

        if ($from->diffInDays($to) > self::MAX_SPAN_DAYS) {
            throw AgentToolFailure::validation(
                'That range is too wide. Ask for at most '.self::MAX_SPAN_DAYS.' days at a time.'
            );
        }

        return [$from, $to];
    }

    private function where(Appointment $appointment): ?string
    {
        $parts = array_filter([
            $appointment->location_address,
            $appointment->location_postcode,
            $appointment->location_city,
        ]);

        return $parts === [] ? null : implode(', ', $parts);
    }
}
