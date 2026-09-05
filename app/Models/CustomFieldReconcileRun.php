<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * One attempt to make the tenant's schema match its definitions.
 *
 * The row is opened BEFORE the first introspection query and closed in a
 * finally-shaped catch, so a process killed mid-ALTER leaves a trace instead
 * of nothing. That ordering is the rule the backup ledger learned the
 * expensive way: one line of setup sitting two lines above the try left 74
 * dead runs reading `running` for a week.
 */
class CustomFieldReconcileRun extends Model
{
    use HasUuids;

    protected $connection = 'tenant';

    public const STATUS_RUNNING = 'running';
    public const STATUS_OK = 'ok';
    public const STATUS_FAILED = 'failed';

    /** An admin saved a definition. */
    public const TRIGGER_SAVE = 'save';

    /** Someone ran fields:reconcile. */
    public const TRIGGER_COMMAND = 'command';

    /** A failed definition was re-submitted. */
    public const TRIGGER_RETRY = 'retry';

    protected $fillable = [
        'host', 'triggered_by', 'status', 'intents', 'applied', 'error',
        'started_at', 'finished_at', 'request_id', 'actor_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'intents' => 'array',
            'applied' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
