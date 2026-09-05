<?php

namespace App\Domain\CustomFields;

/**
 * One tenant's custom fields, resolved once and then answered without touching
 * the database.
 *
 * Two implementations, deliberately:
 *
 *  - ReferenceFieldCatalogue reads the definition rows and resolves the locale
 *    cascade every time it is built. It is the readable, obviously-correct
 *    one, and it is written FIRST.
 *  - The compiled class emits the same answers as literal arrays, generated
 *    per tenant and cached on disk.
 *
 * A golden test asserts the two agree over a fixture covering every registered
 * type. That test is the only thing that makes a code generator safe to change
 * later, and it can only exist because the interpreter existed first.
 *
 * Instance methods rather than statics: an interface cannot promise a static
 * method in a way two implementations can satisfy polymorphically, and the
 * loader caches the instance anyway, so the "no container, no constructor
 * work" property the study asks for is kept by the loader rather than by the
 * shape of the class.
 */
interface CompiledCatalogueInterface
{
    /**
     * A fingerprint of the definitions this catalogue was built from, so a
     * caller can tell two catalogues apart without comparing them field by
     * field.
     */
    public function version(): string;

    /**
     * The readable custom-value columns for a host, column => field type key.
     *
     * Used to build explicit SELECT lists: the agenda's current `select *`
     * would haul every display-only TEXT column on every week view.
     *
     * @return array<string, string>
     */
    public function columns(string $host): array;

    /**
     * What the reconciler must make true, one entry per column it owns.
     *
     * The reconciler reads THIS rather than the definition rows, so there is
     * one derivation path and one artefact both it and every reader agree on
     * — and so the catalogue is load-bearing from its first commit rather
     * than dead code waiting for values to exist.
     *
     * @return array<int, array{num:int, column:string, type:string, spec:ColumnSpec, wants_index:bool, index_name:string, state:string}>
     */
    public function desiredSchema(string $host): array;

    /**
     * Everything a reader needs about each field, with the label already
     * resolved for the given locale.
     *
     * The role rules ride along as plain id lists so that the projector can
     * decide visibility from an array, without the read path calling back
     * into the authorization stack once per row per field — the stance
     * AppointmentActionRegistry::menuFor() already takes with its $allows
     * callback, generalised.
     *
     * @return array<int, array{
     *     num:int, column:string, type:string, label:string, help_text:?string,
     *     icon:?string, colour:?string, colour_dark:?string, size:int,
     *     slot:?string, section:?string, position:int, is_required:bool,
     *     items:?array, hidden_role_ids:array<int,string>,
     *     readonly_role_ids:array<int,string>, required_role_ids:array<int,string>
     * }>
     */
    public function fields(string $host, string $locale): array;

    /** Locales this catalogue carries a label for. @return array<int, string> */
    public function locales(): array;

    /** The hosts that have at least one definition. @return array<int, string> */
    public function hosts(): array;
}
