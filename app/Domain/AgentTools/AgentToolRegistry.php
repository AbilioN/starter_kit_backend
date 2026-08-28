<?php

namespace App\Domain\AgentTools;

/**
 * The only way the executor obtains a handler.
 *
 * The catalogue's `handler` column names a class, but it is looked up HERE
 * rather than instantiated — `app($columnValue)` would turn a text column into
 * arbitrary class instantiation.
 *
 * Registration is an explicit list in AgentToolServiceProvider, not tag-based
 * discovery. Nothing in this codebase is tag-discovered, and here it would mean
 * a class becomes callable by an AI agent merely by existing in a folder.
 * Adding a tool should be a visible line in a diff.
 */
final class AgentToolRegistry
{
    /** @var array<string, AgentToolInterface> keyed by tool name */
    private array $byName = [];

    /** @var array<string, AgentToolInterface> keyed by FQCN */
    private array $byClass = [];

    public function register(AgentToolInterface $tool): void
    {
        $this->byName[$tool->name()] = $tool;
        $this->byClass[$tool::class] = $tool;
    }

    /** Resolves by the catalogue row's `handler` column. Null when unregistered. */
    public function forHandler(string $handler): ?AgentToolInterface
    {
        return $this->byClass[$handler] ?? null;
    }

    public function forName(string $name): ?AgentToolInterface
    {
        return $this->byName[$name] ?? null;
    }

    /** @return array<int, AgentToolInterface> */
    public function all(): array
    {
        return array_values($this->byName);
    }
}
