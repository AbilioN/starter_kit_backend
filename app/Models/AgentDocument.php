<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * A document the tenant makes available to its assistant, and the text of it
 * the agent can search.
 *
 * Tenant-scoped by living on the tenant connection (the default one, switched
 * per request), so there is no cross-tenant read to guard against here.
 */
class AgentDocument extends Model
{
    use HasUuids;

    /** Staff only. The default, because publishing must be a deliberate act. */
    public const AUDIENCE_INTERNAL = 'internal';

    /** Anyone the tenant's assistant serves, including their end users. */
    public const AUDIENCE_PUBLISHED = 'published';

    public const AUDIENCES = [self::AUDIENCE_INTERNAL, self::AUDIENCE_PUBLISHED];

    protected $fillable = ['title', 'description', 'audience', 'file_path', 'content', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * What this actor is allowed to read, expressed as a QUERY rather than as
     * a rule each caller remembers.
     *
     * Both document tools go through here, which is the whole point: the
     * user-side ones were registered before the table had any notion of
     * audience, and were safe only because nothing could write to it. A filter
     * repeated in two callers is a filter that will be forgotten in the third.
     *
     * Anything that is not an admin is treated as an end user — fail-closed,
     * so a future actor type nobody updated this for sees the published set
     * rather than everything.
     */
    public function scopeReadableBy(Builder $query, ?string $actorType): Builder
    {
        return $query
            ->where('is_active', true)
            ->when($actorType !== 'admin', fn (Builder $q) => $q->where('audience', self::AUDIENCE_PUBLISHED));
    }
}
