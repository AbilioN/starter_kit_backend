<?php

namespace App\Models\Concerns;

/**
 * Makes a model a host for tenant-defined fields, and — the important half —
 * makes its custom values invisible to everything that has not asked for them
 * through the projector.
 *
 * ## Why this is a trait on the model and not a check in a controller
 *
 * Custom values are real columns, so the moment the reconciler adds one it is
 * in `$this->attributes` and rides along with every `toArray()`, every
 * `toJson()`, every raw model returned as a response body, every broadcast
 * payload and every agent-tool result. This codebase has no API Resource
 * layer and no base-controller envelope, so there is no single place a
 * response is shaped — which means "remember to strip them" would be a rule
 * enforced by discipline across every present and future call site.
 *
 * Three concrete leaks this closes, two of them live today:
 *
 * 1. `AppointmentController` returns the raw Eloquent model as `data` from
 *    store(), update() and changeStatus(). `Appointment` declares no
 *    `$hidden`, so every custom value would ship to every viewer from the
 *    moment its column existed.
 * 2. `HasAuditLog::logAudit()` strips `$hidden` from oldValues/newValues
 *    before writing them — and `audit_logs` is **immutable by cross-cutting
 *    decision**: no delete, no update, not even for a super admin. Custom
 *    fields are exactly where a tenant puts a national ID or a medical note.
 *    Without this trait those land in a table nobody can ever clean, and
 *    there is no later fix.
 * 3. Anything added afterwards that serialises a host model.
 *
 * ## Why the key list comes from the attributes and not from the catalogue
 *
 * `getHidden()` derives the list by regex over the attribute keys actually
 * present on the row. That is deliberate. If it asked the catalogue, then a
 * tenant whose `custom_field_definitions` table has not been migrated yet, or
 * a Horizon worker whose catalogue failed to compile, or a restore that left
 * schema and definitions disagreeing, would all fall back to "hide nothing" —
 * failing open, on the one thing that must fail closed.
 */
trait HasTenantFields
{
    /**
     * Every `cf_*` attribute is hidden by default. The ONLY thing that
     * un-hides one is ProjectCustomFieldsUseCase, which takes a viewer as a
     * required argument.
     *
     * @return array<int, string>
     */
    public function getHidden(): array
    {
        return array_values(array_unique(array_merge(
            parent::getHidden(),
            $this->tenantFieldColumns(),
        )));
    }

    /**
     * The custom-value columns present on this row, live and retired alike.
     *
     * Retired columns (`cf_7_retired_260904`) still hold data and must stay
     * hidden for exactly as long as they exist — retiring a field is not a
     * decision to publish what it held.
     *
     * @return array<int, string>
     */
    public function tenantFieldColumns(): array
    {
        return array_values(array_filter(
            array_keys($this->attributes),
            fn (string $key) => preg_match('/^cf_\d+(_retired_\d{6})?$/', $key) === 1,
        ));
    }

    /**
     * The raw stored values, keyed by column. For the projector, which is the
     * only thing that should be calling this.
     *
     * @return array<string, mixed>
     */
    public function tenantFieldValues(): array
    {
        $values = [];

        foreach ($this->tenantFieldColumns() as $column) {
            $values[$column] = $this->attributes[$column] ?? null;
        }

        return $values;
    }

    /**
     * Write custom values.
     *
     * `$fillable` on Appointment and User is a fixed list, and `cf_*` columns
     * are invented at runtime, so `update(['cf_7' => 'x'])` silently discards
     * the key and answers 200 with nothing stored — the "looked like it
     * worked" failure this feature refuses everywhere else. forceFill is the
     * only way through, which is precisely why it must be handed an explicit
     * whitelist.
     *
     * @param  array<string, mixed>  $values  column => value
     * @param  array<int, string>  $allowedColumns  from the compiled catalogue,
     *         already narrowed to what this viewer may write
     */
    public function setTenantFieldValues(array $values, array $allowedColumns): void
    {
        $allowed = array_flip($allowedColumns);
        $writable = [];

        foreach ($values as $column => $value) {
            // Two belts. The whitelist is the real gate; the pattern is here
            // so that a caller who assembles the whitelist wrongly still
            // cannot reach a column outside this feature's namespace —
            // the same reflex as TenantProvisioningService validating a
            // database name before interpolating it into a raw statement.
            if (! isset($allowed[$column]) || preg_match('/^cf_\d+$/', $column) !== 1) {
                continue;
            }

            $writable[$column] = $value;
        }

        if ($writable !== []) {
            $this->forceFill($writable);
        }
    }
}
