<?php

namespace App\Models;

use App\Domain\CustomFields\CustomFieldStates;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One tenant-defined field, as declared. See the migration for what each
 * column decides.
 *
 * The connection is pinned, unlike Appointment. These rows are read from a
 * queued job, from console commands and from a health sweep, none of which go
 * through IdentifyTenant — so relying on `database.default` having been
 * flipped would read the landlord, or the previous tenant on a long-lived
 * Horizon worker.
 */
class CustomFieldDefinition extends Model
{
    use HasUuids;

    protected $connection = 'tenant';

    // The lifecycle lives in the domain layer so the pure planner can reason
    // about it without an Eloquent model. Mirrored here only so call sites
    // that already hold the model read naturally.
    public const STATE_PENDING = CustomFieldStates::PENDING;
    public const STATE_LIVE = CustomFieldStates::LIVE;
    public const STATE_RETIRING = CustomFieldStates::RETIRING;
    public const STATE_RETIRED = CustomFieldStates::RETIRED;
    public const STATE_PURGED = CustomFieldStates::PURGED;
    public const STATE_FAILED = CustomFieldStates::FAILED;
    public const STATE_MISSING = CustomFieldStates::MISSING;

    protected $fillable = [
        'host', 'num', 'column_name', 'field_type', 'items',
        'is_filterable', 'section', 'slot', 'position',
        'icon', 'colour', 'colour_dark', 'font_size',
        'pattern', 'is_required',
        'state', 'state_error_code', 'state_error_params', 'reconciled_at',
        'created_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'items' => 'array',
            'is_filterable' => 'boolean',
            'is_required' => 'boolean',
            'num' => 'integer',
            'position' => 'integer',
            'font_size' => 'integer',
            'state_error_params' => 'array',
            'reconciled_at' => 'datetime',
        ];
    }

    public function labels(): HasMany
    {
        return $this->hasMany(CustomFieldLabel::class, 'definition_id');
    }

    public function roleRules(): HasMany
    {
        return $this->hasMany(CustomFieldRoleRule::class, 'definition_id');
    }

    /**
     * The states whose column the accessor may read from.
     *
     * `retired` is excluded even though its data survives: the column has been
     * renamed, so selecting it would error. `missing` is excluded because the
     * column is gone — that demotion is what keeps a hand-dropped column from
     * taking a screen down with SQLSTATE 42S22.
     */
    public function scopeReadable(Builder $query): Builder
    {
        return $query->whereIn('state', CustomFieldStates::readable());
    }

    /**
     * The states the reconciler must account for: everything that owns, or is
     * about to own, a column on the host table.
     */
    public function scopeReconcilable(Builder $query): Builder
    {
        return $query->whereIn('state', CustomFieldStates::reconcilable());
    }

    /**
     * The states that consume the tenant's paid quota.
     *
     * Deliberately not "every row": rows are never deleted here, so counting
     * all of them would let a field a tenant retired last year permanently
     * occupy a slot they are still paying for. The STRUCTURAL ceilings count
     * differently — a retired column still occupies the table and a row
     * version — which is why those are computed from the real schema rather
     * than from this.
     */
    public function scopeCountsTowardPlanLimit(Builder $query): Builder
    {
        return $query->whereIn('state', CustomFieldStates::countsTowardPlanLimit());
    }
}
