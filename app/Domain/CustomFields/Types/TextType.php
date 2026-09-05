<?php

namespace App\Domain\CustomFields\Types;

use App\Domain\CustomFields\ColumnSpec;
use App\Domain\CustomFields\FieldTypeInterface;

/**
 * Single-line text. The only type Part 1 ships.
 *
 * It is the one that exercises every mechanism the rest of the feature is
 * built on — including the one that matters most, filterability deciding the
 * column TYPE and not just the index.
 */
final class TextType implements FieldTypeInterface
{
    /**
     * 190, not the study's 70.
     *
     * The binding constraint here is MySQL's 3072-byte index limit under
     * utf8mb4: 190 x 4 = 760 bytes, comfortably inside it, and 190/191 is
     * already this stack's convention for an indexed string. 70 is the other
     * product's number. The decision the study actually asks for — that the
     * width is CHOSEN rather than defaulted — is what carries over.
     */
    public const FILTERABLE_LENGTH = 190;

    public function key(): string
    {
        return 'text';
    }

    public function columnSpec(bool $isFilterable): ColumnSpec
    {
        // The study's first pitfall, "the row that got too wide", implemented
        // in one branch. Twenty VARCHAR(255) columns reach InnoDB's row-size
        // ceiling and every insert starts failing; TEXT stores off-page and
        // costs the row a pointer. So the field a tenant only ever LOOKS at
        // is cheap, and only the ones they filter on spend row budget.
        return $isFilterable
            ? new ColumnSpec(type: 'varchar', length: self::FILTERABLE_LENGTH, indexable: true)
            : new ColumnSpec(type: 'text', indexable: false);
    }

    public function canFilter(): bool
    {
        return true;
    }

    public function toMachineValue(mixed $stored): mixed
    {
        return $stored === null ? null : (string) $stored;
    }

    public function toText(mixed $stored, string $locale, array $options = []): string
    {
        // trim() and nothing else. No escaping, no wrapping, no markup — the
        // consumer draws it, and there is more than one consumer.
        return $stored === null ? '' : trim((string) $stored);
    }
}
