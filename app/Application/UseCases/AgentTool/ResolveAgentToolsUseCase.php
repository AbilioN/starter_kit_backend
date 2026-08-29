<?php

namespace App\Application\UseCases\AgentTool;

use App\Domain\AgentTools\ToolSchema;
use App\Domain\AgentTools\AdminToolRegistry;
use App\Models\AgentTool as AgentToolModel;
use Illuminate\Support\Facades\Log;

/**
 * The tools one agent may use on this turn, in the shape OpenAI expects.
 *
 * Reads the landlord catalogue live, exactly as ProcessOpenAIRequest already
 * reads the agent profile: an operator attaching a tool takes effect on the very
 * next message, with no deploy, no restart and no propagation step.
 *
 * Returns an empty array whenever the feature is off, the agent has no profile,
 * or nothing is attached — and the caller then omits `tools` from the payload
 * entirely, which is what keeps an agent without tools byte-identical to before
 * this feature existed.
 */
class ResolveAgentToolsUseCase
{
    public function __construct(private AdminToolRegistry $registry) {}

    /**
     * @return array{specs: array<int, array>, names: array<int, string>}
     */
    public function execute(?string $agentProfileId): array
    {
        if ($agentProfileId === null || (string) config('agent_tools.worker_key') === '') {
            return ['specs' => [], 'names' => []];
        }

        $rows = AgentToolModel::query()
            ->where('is_active', true)
            ->whereHas('agentProfiles', fn ($query) => $query->where('agent_profiles.id', $agentProfileId))
            ->orderBy('name')
            ->get();

        $specs = [];
        $names = [];

        foreach ($rows as $row) {
            $handler = $this->registry->forHandler($row->handler);

            if (! $handler) {
                // Advertising a tool the executor would then refuse teaches the
                // model to expect a capability that is not there, and wastes a
                // call on discovering it.
                Log::warning('Agent tool catalogue names an unregistered handler; not offered', [
                    'tool' => $row->name,
                    'handler' => $row->handler,
                ]);

                continue;
            }

            $parameters = is_array($row->parameters_override) && $row->parameters_override !== []
                ? $row->parameters_override
                : $handler->parameters();

            $specs[] = [
                'type' => 'function',
                'function' => [
                    'name' => $row->name,
                    // The row's description wins: it is the operator-editable
                    // knob that steers which tool the model reaches for.
                    'description' => $row->description ?: $handler->description(),
                    'parameters' => ToolSchema::forWire($parameters),
                ],
            ];

            $names[] = $row->name;
        }

        return ['specs' => $specs, 'names' => $names];
    }
}
