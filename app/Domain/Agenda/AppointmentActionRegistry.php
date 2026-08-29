<?php

namespace App\Domain\Agenda;

use App\Models\Appointment;

/**
 * The actions a card can offer, in an explicit list.
 *
 * Explicit for the same reason the agent tool registries are: nothing should
 * become invocable merely by existing in a folder. Adding an action is a line
 * in a diff someone reviewed.
 */
final class AppointmentActionRegistry
{
    /** @var array<string, AppointmentActionInterface> */
    private array $actions = [];

    public function register(AppointmentActionInterface $action): void
    {
        $this->actions[$action->key()] = $action;
    }

    public function get(string $key): ?AppointmentActionInterface
    {
        return $this->actions[$key] ?? null;
    }

    /** @return array<int, AppointmentActionInterface> */
    public function all(): array
    {
        return array_values($this->actions);
    }

    /**
     * The menu for one appointment: only actions that apply to it, and only
     * those the viewer may use, already grouped into sub-menus.
     *
     * @param  callable(?string): bool  $allows  answers "may this person use an
     *         action requiring this slug?" — passed in rather than resolved here
     *         so the registry stays free of the authorization stack.
     * @return array<string, array<int, array>>  keyed by sub-menu
     */
    public function menuFor(Appointment $appointment, callable $allows): array
    {
        $menu = [];

        foreach ($this->actions as $action) {
            if (! $allows($action->permission()) || ! $action->isAvailableFor($appointment)) {
                continue;
            }

            $menu[$action->group() ?? 'general'][] = [
                'key' => $action->key(),
                'label' => $action->label(),
                'icon' => $action->icon(),
                ...$action->describe($appointment),
            ];
        }

        return $menu;
    }
}
