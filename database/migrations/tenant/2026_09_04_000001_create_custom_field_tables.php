<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant-defined fields: the four tables a field is declared in.
 *
 * Design: root docs/19-tenant-defined-fields.md, from the study in
 * tenant-defined-fields.md.
 *
 * The value columns themselves are NOT here. They are `cf_{num}` on the host
 * table, added at runtime by the reconciler from these rows — that is the
 * whole point of the feature. This migration only creates the place a field
 * is *declared*.
 */
return new class extends Migration
{
    // The five newest tenant migrations omit this and work only because
    // `tenant:migrate` passes --database=tenant. This feature's code runs from
    // console commands, queued jobs and a health sweep, where `database.default`
    // is whatever was last configured — so the connection is pinned, matching
    // the older house style (2026_08_08_000001_create_templates_table.php).
    protected $connection = 'tenant';

    public function up(): void
    {
        // The only place a custom field is declared. Nothing else in the
        // system stores what a field IS.
        Schema::create('custom_field_definitions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Which entity the field hangs off. A key into a code registry
            // (CustomFieldHostRegistry), never a table name a tenant typed —
            // a tenant-supplied identifier reaching the reconciler is a
            // tenant-supplied identifier reaching ALTER TABLE.
            //
            // On the row from day one so tenant-defined child entities become
            // a registry entry later rather than a migration.
            $table->string('host', 40);

            // The stable handle. The storage column is `cf_{num}`, and num is
            // NEVER reused — not after retiring a field, not after purging
            // one. A reused handle means a new field silently inherits the old
            // one's data, its index and its audit history.
            $table->unsignedInteger('num');

            // Materialised rather than recomputed from `num`. This is what
            // information_schema gets diffed against, and a name computed in
            // two places will eventually differ in one. 64 rather than 20
            // because a retired name (`cf_123_retired_260904`) is longer than
            // the live one it replaces.
            $table->string('column_name', 64);

            // One key from FieldTypeRegistry. Not a PHP enum: there are zero
            // enums in app/, and the house pattern for a closed set the code
            // branches on is an explicitly-registered registry.
            $table->string('field_type', 30);

            // Literal choices for list types. Read by the catalogue only, and
            // never queried in SQL — JSON path predicates are not portable
            // across the sqlite/mysql split this suite runs on.
            $table->json('items')->nullable();

            // A STORAGE flag that also happens to surface a filter, not a
            // display toggle. It is the single input deciding VARCHAR+index
            // versus TEXT: an indexable column costs row bytes, a display-only
            // one stores off-page and costs the row almost nothing. Named this
            // way so nobody later "simplifies" it into a checkbox on a form.
            $table->boolean('is_filterable')->default(false);

            // Where it appears. `section` is the form group; `slot` is a named
            // place in an existing list/card element declared by the host
            // (card.badges, card.subtitle) rather than an appended column —
            // a tenant who can only append runs out of horizontal room in a
            // week. NULL slot = form only.
            $table->string('section', 60)->nullable();
            $table->string('slot', 60)->nullable();

            // Explicit ordering. Ordering by row id is the trap the study
            // names, and it springs the first time a tenant inserts a field in
            // the middle. Type matches appointment_types.position.
            $table->unsignedSmallInteger('position')->default(0);

            // Presentation. Both colours, always: the server does not know
            // whether the reader is in light or dark mode, so it sends both
            // and lets the client's own theme choose.
            $table->string('icon', 60)->nullable();
            $table->string('colour', 9)->nullable();
            $table->string('colour_dark', 9)->nullable();
            $table->unsignedTinyInteger('font_size')->default(0); // 0 = inherit

            // A regex, validated to compile before it is stored — otherwise a
            // tenant can save a pattern that 422s every subsequent write on
            // that host and cannot be edited out without a DBA.
            $table->string('pattern', 200)->nullable();

            // Required for everyone. Per-role obligations live in
            // custom_field_role_rules.
            $table->boolean('is_required')->default(false);

            // The lifecycle, and the loud part of this feature.
            //   pending  → written, column not yet made
            //   live     → column exists and matches
            //   retiring → renamed away, index dropped
            //   retired  → column parked as cf_N_retired_YYMMDD
            //   purged   → column actually dropped by an operator
            //   failed   → the reconcile refused; state_error_code says why
            //   missing  → the column vanished under us (a restore, a hand-drop)
            //
            // An explicit state and NOT soft deletes: a trashed row is invisible
            // to max(num) under the global scope while unique(host, num) still
            // sees it, so the first create after any delete would collide
            // forever. The reconciler also has to see retired rows to know
            // those columns are its business.
            $table->string('state', 16)->default('pending');

            // A machine code plus its parameters, translated at read time.
            // A frozen-language sentence produced inside a queued job — whose
            // locale is whoever dispatched it — and then shown verbatim on an
            // otherwise translated screen is a bug in a product whose API
            // answers in the tenant's language.
            $table->string('state_error_code', 64)->nullable();
            $table->json('state_error_params')->nullable();

            $table->timestamp('reconciled_at')->nullable();

            // No foreign key, matching every other admin reference in this
            // schema: an admin is hard-deletable and a field must outlive the
            // person who created it.
            $table->string('created_by_admin_id', 36)->nullable();

            $table->timestamps();

            $table->unique(['host', 'num']);
            $table->unique(['host', 'column_name']);
            $table->index(['host', 'state']);
            $table->index(['host', 'position']);
        });

        // One row per language. A translation of a template is a whole
        // template; a translation of a label is a label — so the type, the
        // storage and the filterability stay on the parent and do not
        // duplicate per locale. They could not live here anyway: the
        // reconciler reads the parent to decide one column's type, and four
        // locales would give it four answers.
        Schema::create('custom_field_labels', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // The table is named explicitly. Laravel would infer `definitions`
            // from `definition_id` and the migration would fail — which is why
            // 2026_08_29_000004 spells out `->constrained('appointment_types')`
            // even where inference would have worked.
            $table->foreignUuid('definition_id')
                ->constrained('custom_field_definitions')
                ->cascadeOnDelete();

            // Bounded by config('app.available_locales'), never by the
            // tenant's own locales.enabled — enabled says what a tenant
            // OFFERS, which is not the same as what the platform can render.
            $table->string('locale', 10);

            $table->string('label', 120);
            $table->string('help_text', 255)->nullable();
            $table->string('placeholder', 120)->nullable();
            $table->timestamps();

            $table->unique(['definition_id', 'locale']);
            $table->index('locale');
        });

        // The study's role THRESHOLD, as sets — because there is nothing here
        // to threshold against. `roles` has no level, rank or ordering column,
        // and an admin holds a SET of roles resolved with OR, so there is no
        // single "the user's role" to compare against a cutoff.
        Schema::create('custom_field_role_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('definition_id')
                ->constrained('custom_field_definitions')
                ->cascadeOnDelete();

            // A UUID, never a slug: UpdateRoleUseCase regenerates
            // slug = Str::slug($name) on every rename, so a stored slug
            // silently stops matching the moment a tenant renames a role.
            //
            // No foreign key. Nothing in roles/permissions/role_permissions/
            // admin_roles has one, and adding the first would make role
            // deletion start failing — a new feature changing frozen-core
            // behaviour. A rule whose role no longer exists can no longer
            // match anyone's role set, so it is inert rather than dangerous,
            // and the sweep deletes it.
            $table->string('role_id', 36);

            // hidden | readonly | required
            $table->string('rule', 10);

            $table->timestamps();

            $table->unique(['definition_id', 'role_id', 'rule']);
            $table->index('role_id');
        });

        // The ledger. Written BEFORE the work starts, so a process killed
        // mid-ALTER leaves a trace rather than nothing.
        //
        // The rule this table exists to enforce: every path between the row
        // being opened and the catch must be inside the try. That was broken
        // once by a single line in the backup system and 74 dead runs read as
        // `running` for a week.
        Schema::create('custom_field_reconcile_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('host', 40);

            // save | command | retry — how this run was triggered, so an
            // unexpected burst can be attributed.
            $table->string('trigger', 20);

            // running | ok | failed
            $table->string('status', 16)->default('running');

            // The plan, written before execution; then what was actually
            // applied, appended statement by statement. The difference between
            // the two is what a killed run leaves behind.
            $table->json('intents')->nullable();
            $table->json('applied')->nullable();

            $table->text('error')->nullable();

            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();

            // The observability contract from roadmap 5.1: one id ties this
            // run to the request that asked for it and to the Horizon job that
            // did it.
            $table->string('request_id', 64)->nullable();
            $table->string('actor_admin_id', 36)->nullable();

            $table->timestamps();

            $table->index(['status', 'started_at']);
            $table->index(['host', 'started_at']);
        });
    }

    public function down(): void
    {
        // Children first: both carry a cascading foreign key to definitions.
        Schema::dropIfExists('custom_field_reconcile_runs');
        Schema::dropIfExists('custom_field_role_rules');
        Schema::dropIfExists('custom_field_labels');
        Schema::dropIfExists('custom_field_definitions');
    }
};
