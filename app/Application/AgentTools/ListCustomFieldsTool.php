<?php

namespace App\Application\AgentTools;

use App\Application\CustomFields\FieldViewerFactory;
use App\Application\UseCases\CustomField\ProjectCustomFieldsUseCase;
use App\Domain\AgentTools\AgentToolContext;
use App\Domain\AgentTools\AgentToolInterface;
use App\Domain\AgentTools\AgentToolResult;
use App\Domain\CustomFields\CustomFieldHostRegistry;
use App\Models\Admin;
use App\Models\CustomFieldDefinition;

/**
 * Lets the agent answer questions about the fields this workspace invented.
 *
 * "What extra fields do we track on an appointment?", "is the contract number
 * live yet?", "which of our fields can be filtered?" — questions whose answers
 * are different in every tenant, which is exactly the shape a tool is for.
 *
 * ## It goes through the projector, and that is the whole security story
 *
 * A field the acting admin's roles hide must not be described to them by the
 * assistant either. Listing names and labels sounds harmless right up until
 * the field is called "Salary band" or "Medical notes" — the study's third
 * pitfall is a permission enforced in one renderer and forgotten in the next,
 * and an AI answer is the newest renderer.
 *
 * So this reads ProjectCustomFieldsUseCase::context() rather than the
 * definition rows, because that is the one place `hide_for` is applied. What
 * it adds on top is the operational half the projector deliberately omits —
 * `state`, and whether a field is filterable — which a reader of VALUES has no
 * business seeing but somebody asking ABOUT the fields does.
 *
 * ## The identity comes from the grant
 *
 * There is no authenticated user on this path, and the failure would be silent
 * rather than loud: FieldViewerFactory would receive null, hand back the
 * system viewer, and cheerfully describe every hidden field. The actor is
 * loaded from `$context->actorId` instead — the rule docs/12 §3 records after
 * it caught somebody already.
 */
final class ListCustomFieldsTool implements AgentToolInterface
{
    public function __construct(
        private ProjectCustomFieldsUseCase $customFields,
        private CustomFieldHostRegistry $hosts,
        private FieldViewerFactory $viewers,
    ) {}

    public function name(): string
    {
        return 'list_custom_fields';
    }

    public function description(): string
    {
        return 'List the custom fields this workspace has defined on its records — '
            .'their names, which kind of record they belong to, what type they are, '
            .'whether they can be filtered, and whether they are ready to use.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'entity' => [
                    'type' => 'string',
                    'description' => 'Only fields on this kind of record. Omit for all of them.',
                    // Enumerated from the registry rather than hardcoded, so a
                    // host added later is offered to the model without anyone
                    // remembering to edit this string.
                    'enum' => $this->hosts->keys(),
                ],
            ],
            'additionalProperties' => false,
        ];
    }

    public function permission(): ?string
    {
        // Reading the catalogue, not changing it. `custom-field-manage` runs
        // DDL and no tool should hold it.
        return 'custom-field-read';
    }

    public function isMutating(): bool
    {
        return false;
    }

    public function execute(array $arguments, AgentToolContext $context): AgentToolResult
    {
        $viewer = $this->viewers->forAdmin(Admin::find($context->actorId));

        $requested = $arguments['entity'] ?? null;
        $hostKeys = $requested !== null ? [$requested] : $this->hosts->keys();

        // Read once per host, keyed by column, so the operational half below
        // costs one query rather than one per field.
        $rows = [];

        foreach ($hostKeys as $hostKey) {
            if ($this->hosts->get($hostKey) === null) {
                continue;
            }

            $states = CustomFieldDefinition::query()
                ->where('host', $hostKey)
                ->pluck('is_filterable', 'column_name')
                ->all();

            $lifecycle = CustomFieldDefinition::query()
                ->where('host', $hostKey)
                ->pluck('state', 'column_name')
                ->all();

            foreach ($this->customFields->context($hostKey, $viewer) as $field) {
                $rows[] = [
                    'entity' => $hostKey,
                    'name' => $field['label'],
                    'type' => $field['type'],
                    'filterable' => (bool) ($states[$field['key']] ?? false),
                    // `live` is the only state whose column actually exists and
                    // holds data. Saying so matters: "we have a contract number
                    // field" is misleading while it is still `pending`.
                    'ready' => ($lifecycle[$field['key']] ?? null) === 'live',
                    'shown_on_cards' => $field['slot'] !== null,
                    'help' => $field['help_text'],
                ];
            }
        }

        return AgentToolResult::rows($rows, $context->maxRows);
    }
}
