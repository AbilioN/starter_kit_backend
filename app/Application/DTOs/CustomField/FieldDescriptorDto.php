<?php

namespace App\Application\DTOs\CustomField;

/**
 * What a custom field IS, for one reader — sent once per response.
 *
 * Split from FieldValueDto for a reason that only shows up at scale. A week
 * view returns something like a hundred appointments; repeating a field's
 * label, icon and two colours on every one of them multiplies the tenant's
 * presentation config by the row count, on every request, forever. With a
 * dozen fields defined that is most of the payload.
 *
 * So the descriptor travels once, the values travel per row, and the client
 * joins them on `field`. That also means a screen can draw an EMPTY form —
 * the controls exist because the descriptors do, not because some record
 * happened to have values.
 *
 * `editable` lives here rather than on the value because it depends on the
 * reader, not on the row: a `readonly` rule applies to a person, and it
 * applies to every row they can see.
 *
 * Still values, never markup. `icon` is a name, and both colours are sent so
 * the client's own theme chooses — the server does not know what the reader
 * is looking at.
 */
final class FieldDescriptorDto
{
    public function __construct(
        /** The definition's `num`. What a value record joins on. */
        public readonly int $field,
        /** The storage column, `cf_7`. What a write submits under. */
        public readonly string $key,
        /** Which CONTROL to draw. Never what the field means. */
        public readonly string $type,
        /** The tenant's own name for it, resolved for this reader's language. */
        public readonly string $label,
        public readonly ?string $helpText = null,
        public readonly ?string $icon = null,
        public readonly ?string $colour = null,
        public readonly ?string $colourDark = null,
        /** 0 means inherit. */
        public readonly int $size = 0,
        /** A named place on the card or row. Null means form only. */
        public readonly ?string $slot = null,
        /** Which group of the form it belongs to. */
        public readonly ?string $section = null,
        /**
         * The host's human name for that section.
         *
         * Carried on the descriptor rather than as a separate map in every
         * response: a form groups by `section` and needs a heading for the
         * group, and threading host metadata through the agenda, the user list
         * and every future entity payload to supply one string is a worse
         * trade than repeating it.
         */
        public readonly ?string $sectionLabel = null,
        public readonly int $position = 0,
        /** Whether THIS reader must fill it — is_required, or a role rule. */
        public readonly bool $required = false,
        /** False when a `readonly` rule applies to this reader. */
        public readonly bool $editable = true,
        /** Literal choices, for the list types. */
        public readonly ?array $items = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'field' => $this->field,
            'key' => $this->key,
            'type' => $this->type,
            'label' => $this->label,
            'help_text' => $this->helpText,
            'icon' => $this->icon,
            'colour' => $this->colour,
            'colour_dark' => $this->colourDark,
            'size' => $this->size,
            'slot' => $this->slot,
            'section' => $this->section,
            'section_label' => $this->sectionLabel,
            'position' => $this->position,
            'required' => $this->required,
            'editable' => $this->editable,
            'items' => $this->items,
        ];
    }
}
