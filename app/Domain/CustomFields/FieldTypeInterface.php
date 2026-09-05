<?php

namespace App\Domain\CustomFields;

/**
 * One kind of field a tenant may create.
 *
 * A type fixes four things at once and nothing else: the control the form
 * draws, the column it is stored in, whether it can be filtered, and how the
 * value is formatted for display. Nothing here knows what a field MEANS —
 * that is the tenant's, and the moment a type starts branching on meaning it
 * has crossed the line into the frozen core.
 *
 * Adding a type later is a class plus a registration line. Changing what a
 * type means after tenants have stored data in it is not, which is why the
 * set is small and grows only on the second request for something.
 */
interface FieldTypeInterface
{
    /** Stable machine name; what `custom_field_definitions.field_type` stores. */
    public function key(): string;

    /**
     * The storage this type wants, given whether the tenant asked for it to
     * be filterable.
     *
     * This is where the study's first pitfall is implemented. A field the
     * tenant wants to filter on needs an indexable column, so it gets VARCHAR
     * plus an index; a field that is only ever displayed gets TEXT, which
     * stores off-page and costs the row almost nothing. Same field type to
     * the tenant, different column underneath.
     */
    public function columnSpec(bool $isFilterable): ColumnSpec;

    /**
     * Whether filtering is offerable for this type at all.
     *
     * False for the free-text types: an index on MEDIUMTEXT needs a prefix
     * length (MySQL error 1170 without one), and a substring search over one
     * is a table scan whatever the schema says. Offering it would sell a
     * tenant an index slot that buys nothing.
     */
    public function canFilter(): bool;

    /**
     * The machine value — what a CSV cell, a PDF merge or another service
     * consumes.
     *
     * Separate from toText() because this product's second consumer is not a
     * browser. MySQL returns DECIMAL as a string, and a consumer forced to
     * parse "1.234,50" back has already lost the precision the DECIMAL(14,4)
     * existed for.
     */
    public function toMachineValue(mixed $stored): mixed;

    /**
     * The formatted value, plain — never markup.
     *
     * The strongest rule in the study: the moment a second consumer appears,
     * a finished HTML fragment is worth nothing and the value inside it is
     * unreachable.
     *
     * @param  array<string, mixed>  $options  the definition row, for types
     *         whose display depends on it (a select's option labels)
     */
    public function toText(mixed $stored, string $locale, array $options = []): string;
}
