<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A catalogue entry: which handler is exposed, how it is described to the
 * model, and how much it may return.
 *
 * `is_mutating` here is for display. The authority is the handler's own
 * isMutating(), read from the registry — see AgentToolInterface.
 */
class AgentTool extends Model
{
    use HasUuids, HasFactory;

    protected $connection = 'landlord';

    protected $fillable = [
        'name',
        'handler',
        'description',
        'parameters_override',
        'max_rows',
        'is_active',
        'is_mutating',
    ];

    protected function casts(): array
    {
        return [
            'parameters_override' => 'array',
            'max_rows' => 'integer',
            'is_active' => 'boolean',
            'is_mutating' => 'boolean',
        ];
    }

    public function agentProfiles(): BelongsToMany
    {
        return $this->belongsToMany(AgentProfile::class, 'agent_profile_agent_tool');
    }
}
