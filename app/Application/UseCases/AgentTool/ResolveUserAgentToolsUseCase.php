<?php

namespace App\Application\UseCases\AgentTool;

use App\Domain\AgentTools\ToolSchema;
use App\Domain\AgentTools\UserToolRegistry;

/**
 * The end user's tool set, in the shape OpenAI expects.
 *
 * There is no catalogue to read and nothing to filter: the set is static and
 * identical for every user of every tenant (docs/15 §3). What gates it is what
 * gates the tenant — no worker key, no tools.
 */
class ResolveUserAgentToolsUseCase
{
    public function __construct(private UserToolRegistry $registry) {}

    /**
     * @return array{specs: array<int, array>, names: array<int, string>}
     */
    public function execute(): array
    {
        if ((string) config('agent_tools.worker_key') === '') {
            return ['specs' => [], 'names' => []];
        }

        $specs = [];
        $names = [];

        foreach ($this->registry->all() as $tool) {
            $specs[] = [
                'type' => 'function',
                'function' => [
                    'name' => $tool->name(),
                    'description' => $tool->description(),
                    'parameters' => ToolSchema::forWire($tool->parameters()),
                ],
            ];

            $names[] = $tool->name();
        }

        return ['specs' => $specs, 'names' => $names];
    }
}
