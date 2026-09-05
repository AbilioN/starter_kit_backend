<?php

namespace App\Application\UseCases\AgentTool;

use Illuminate\Support\Facades\File;

/**
 * Builds the system prompt the worker receives: the agent's own persona, then
 * the tool-use block.
 *
 * **Laravel composes this, not the worker.** Only this side knows the profile,
 * the resolved catalogue and the budget config — and composing here means the
 * exact prompt that was sent is reproducible server-side rather than assembled
 * somewhere it cannot be inspected.
 *
 * **The tool block is appended, never substituted.** Replacing the persona
 * would silently undo roadmap 4.3, where a GodAdmin curating that prompt is the
 * product feature.
 *
 * With no tools the persona is returned untouched, so a turn is byte-identical
 * to one from before this existed.
 */
class ComposeAgentSystemPromptUseCase
{
    /**
     * @param  array<int, array>  $toolSpecs  as sent to OpenAI
     */
    public function execute(?string $personaPrompt, array $toolSpecs): ?string
    {
        if ($toolSpecs === []) {
            return $personaPrompt;
        }

        $block = trim($this->renderToolBlock($toolSpecs));

        if ($block === '') {
            return $personaPrompt;
        }

        return $personaPrompt === null || trim($personaPrompt) === ''
            ? $block
            : trim($personaPrompt)."\n\n".$block;
    }

    private function renderToolBlock(array $toolSpecs): string
    {
        $template = $this->template();

        return str_replace(
            ['{{TOOLS}}', '{{MAX_CALLS}}', '{{MAX_ROUNDS}}', '{{TODAY}}'],
            [
                $this->renderTools($toolSpecs),
                (string) config('agent_tools.max_tool_calls'),
                (string) config('agent_tools.max_rounds'),
                $this->today(),
            ],
            $template,
        );
    }

    /**
     * A model has no clock, and without one an agenda tool is unusable.
     *
     * Found live on 2026-09-05: asked what was booked "on Saturday the 12th of
     * September", the model called list_appointments for **2023** — a year it
     * had no way of knowing was wrong. The tool worked perfectly and the answer
     * was still useless.
     *
     * In the tool block rather than the persona, so a turn with no tools stays
     * byte-identical to one from before any of this existed — the property this
     * class's docblock already protects.
     *
     * The tenant's own timezone, because "today" in Lisbon is not today in
     * São Paulo and the agenda is stored in neither.
     */
    private function today(): string
    {
        $timezone = (string) (\App\Helpers\Settings::get('app.timezone') ?: config('app.timezone', 'UTC'));

        try {
            $now = \Carbon\CarbonImmutable::now($timezone);
        } catch (\Throwable) {
            $now = \Carbon\CarbonImmutable::now('UTC');
            $timezone = 'UTC';
        }

        return sprintf(
            'Today is %s, %s (timezone %s).',
            $now->format('l'),
            $now->toDateString(),
            $timezone,
        );
    }

    /**
     * Rendered from the same specs that go into the payload — never written by
     * hand. A hardcoded list would advertise tools this agent does not have on
     * this plan, which wastes budget on refusals and teaches the model to
     * expect capabilities that are not there.
     */
    private function renderTools(array $toolSpecs): string
    {
        $lines = [];

        foreach ($toolSpecs as $spec) {
            $function = $spec['function'] ?? [];
            $lines[] = sprintf('- `%s` — %s', $function['name'] ?? '?', $function['description'] ?? '');

            $arguments = $this->describeArguments($function['parameters'] ?? []);

            if ($arguments !== '') {
                $lines[] = '  Arguments: '.$arguments;
            }
        }

        return implode("\n", $lines);
    }

    private function describeArguments(array $schema): string
    {
        $properties = $schema['properties'] ?? [];
        $required = $schema['required'] ?? [];
        $parts = [];

        foreach ($properties as $name => $rules) {
            $type = $rules['format'] ?? $rules['type'] ?? 'string';
            $parts[] = in_array($name, $required, true)
                ? "{$name} ({$type}, required)"
                : "{$name} ({$type})";
        }

        return implode(', ', $parts);
    }

    private function template(): string
    {
        $path = resource_path('prompts/agent-tool-use.md');

        // A missing template must not take the chat down with it: the agent
        // keeps its persona and simply stops being told about its tools.
        return File::exists($path) ? File::get($path) : '';
    }
}
