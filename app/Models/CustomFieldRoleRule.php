<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * "This role may not see / may not edit / must fill this field."
 *
 * The study models these as a role THRESHOLD — "mandatory for everyone except
 * administrators". There is nothing here to threshold against: `roles` has no
 * level, rank or ordering column, nothing in app/ orders roles, and an admin
 * holds a SET of them. So they are explicit sets, resolved deny-wins.
 */
class CustomFieldRoleRule extends Model
{
    use HasUuids;

    protected $connection = 'tenant';

    /** Not returned at all — omitted from the payload, never nulled. */
    public const RULE_HIDDEN = 'hidden';

    /** Returned, but a submitted value is dropped and reported. */
    public const RULE_READONLY = 'readonly';

    /** Must be filled, for holders of this role only. */
    public const RULE_REQUIRED = 'required';

    public const RULES = [self::RULE_HIDDEN, self::RULE_READONLY, self::RULE_REQUIRED];

    protected $fillable = ['definition_id', 'role_id', 'rule'];

    public function definition(): BelongsTo
    {
        return $this->belongsTo(CustomFieldDefinition::class, 'definition_id');
    }
}
