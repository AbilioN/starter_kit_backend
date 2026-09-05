<?php

namespace App\Domain\CustomFields;

/**
 * An entity that can carry tenant-defined fields.
 *
 * A code registry, not a table. The set of hostable entities is the core's;
 * only the choice among them is the tenant's. That is not a hedge — a host
 * names a real table the reconciler will run ALTER against, decides which
 * permission gates a write to it, and decides who may read a row at all. By
 * the study's own test, all three are code paths branching on meaning, so the
 * host belongs to the frozen core however configurable it looks.
 *
 * Adding one is a class plus one registration line in
 * CustomFieldServiceProvider, reviewed in a diff — the same stance
 * AppointmentActionRegistry takes: nothing becomes invocable merely by
 * existing in a folder.
 */
interface CustomFieldHostInterface
{
    /** Stable machine name; what `custom_field_definitions.host` stores. */
    public function key(): string;

    /** The real table the reconciler adds columns to. */
    public function table(): string;

    /** @return class-string<\Illuminate\Database\Eloquent\Model> */
    public function modelClass(): string;

    /**
     * The RBAC slug required to WRITE a value on this host — distinct from
     * `custom-field-manage`, which is about defining fields. Someone who may
     * edit an appointment may fill its custom fields; that does not make them
     * someone who may run DDL.
     */
    public function writePermission(): string;

    /**
     * The `settings.features.*` key deciding whether this tenant sees the
     * host at all. Absence means ON, matching the plan form and docs/18 §7 —
     * reading absence as off would take the feature from every tenant that
     * predates it.
     */
    public function featureFlag(): string;

    /**
     * Named places a value may surface outside the form — 'card.badges',
     * 'card.subtitle'. The study uses a column NUMBER, and its reasoning
     * carries ("a tenant who can only append columns runs out of horizontal
     * room in a week"), but the vocabulary does not: every list in this panel
     * is hand-written markup with fixed cells, so an integer index would have
     * nothing to index.
     *
     * @return array<string, string> slot key => human description
     */
    public function slots(): array;

    /**
     * Groups a field can be placed in on the form.
     *
     * @return array<string, string> section key => human description
     */
    public function sections(): array;

    /**
     * Columns the reconciler must never touch even if something asks it to.
     *
     * A second belt: the reconciler already refuses any name that is not
     * `cf_<digits>`, which makes "it dropped starts_at" structurally
     * impossible rather than carefully avoided. This exists so a host can
     * declare intent explicitly, and so the list is somewhere a reviewer can
     * read it.
     *
     * @return array<int, string>
     */
    public function reservedColumns(): array;

    /** How much of this table's structural budget custom fields may spend. */
    public function ceilings(): HostCeilings;
}
