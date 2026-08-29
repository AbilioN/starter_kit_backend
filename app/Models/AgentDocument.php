<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * A document the tenant publishes for its users, and the text of it the agent
 * can search.
 *
 * Tenant-scoped by living on the tenant connection (the default one, switched
 * per request), so there is no cross-tenant read to guard against here.
 */
class AgentDocument extends Model
{
    use HasUuids;

    protected $fillable = ['title', 'description', 'file_path', 'content', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
