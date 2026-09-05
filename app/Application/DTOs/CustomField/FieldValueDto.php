<?php

namespace App\Application\DTOs\CustomField;

/**
 * One record's value for one custom field.
 *
 * Deliberately small: everything about how the field LOOKS lives in
 * FieldDescriptorDto and is sent once per response, not once per row. A
 * client joins the two on `field`.
 *
 * **Values, never markup.** The strongest rule in the study, and the one with
 * no structural defence in this codebase — there is no API Resource layer and
 * no base-controller envelope, so nothing stops a finished HTML fragment
 * except the fact that this class has no field to put one in.
 *
 * It carries both `value` and `text`, which is one more than the study's
 * record, because this product's second consumer is not a browser. A CSV cell
 * and a PDF merge need the machine value; MySQL returns DECIMAL as a string,
 * and a consumer forced to parse "1.234,50" back has already lost the
 * precision the DECIMAL(14,4) existed for.
 */
final class FieldValueDto
{
    public function __construct(
        /** The definition's `num`; joins to a FieldDescriptorDto. */
        public readonly int $field,
        public readonly string $key,
        /** The machine value. */
        public readonly mixed $value,
        /** The formatted value, plain — line breaks preserved. */
        public readonly string $text,
        /** Present when the field renders as a link. */
        public readonly ?string $href = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'field' => $this->field,
            'key' => $this->key,
            'value' => $this->value,
            'text' => $this->text,
            'href' => $this->href,
        ], fn ($v, $k) => $k !== 'href' || $v !== null, ARRAY_FILTER_USE_BOTH);
    }
}
