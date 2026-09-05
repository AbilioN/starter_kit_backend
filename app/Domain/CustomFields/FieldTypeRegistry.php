<?php

namespace App\Domain\CustomFields;

/**
 * The closed set of field types, explicitly registered.
 *
 * A table would have been the other option — appointment_types and
 * appointment_statuses are rows precisely so a tenant can rename a member
 * without a migration. Types are different: code branches on a type's meaning
 * everywhere (which column, which control, which validation, whether an index
 * is legal), so by the study's own test they belong to the frozen core.
 * The tenant picks from a menu; it does not extend the menu.
 */
final class FieldTypeRegistry
{
    /** @var array<string, FieldTypeInterface> */
    private array $types = [];

    public function register(FieldTypeInterface $type): void
    {
        $this->types[$type->key()] = $type;
    }

    public function get(string $key): ?FieldTypeInterface
    {
        return $this->types[$key] ?? null;
    }

    public function require(string $key): FieldTypeInterface
    {
        return $this->types[$key] ?? throw new \InvalidArgumentException(
            "Unknown custom field type [{$key}]. Registered: ".(implode(', ', $this->keys()) ?: 'none').'.'
        );
    }

    /** @return array<int, FieldTypeInterface> */
    public function all(): array
    {
        return array_values($this->types);
    }

    /** @return array<int, string> */
    public function keys(): array
    {
        return array_keys($this->types);
    }

    /**
     * A fingerprint of the registered type classes.
     *
     * Folded into the compiled catalogue's class name: the emitted code bakes
     * in each type's formatting decisions, so changing a type must invalidate
     * every tenant's compiled class without anyone having to remember to
     * flush anything. See FieldCatalogueCompiler.
     */
    public function fingerprintSource(): array
    {
        $classes = array_map(fn (FieldTypeInterface $t) => $t::class, $this->all());
        sort($classes);

        return $classes;
    }
}
