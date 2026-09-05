<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A custom field's name in one language.
 *
 * One row per locale rather than a `translations` JSON column, and rather
 * than columns on the definition itself. The definition row is what the
 * reconciler and the catalogue compiler read to decide one column's type —
 * four locales on that row would give them four answers about one column.
 *
 * Only the human-facing text lives here. The type, the storage decision and
 * the filterability stay on the parent: a translation of a template is a
 * whole template, but a translation of a label is a label.
 */
class CustomFieldLabel extends Model
{
    use HasUuids;

    protected $connection = 'tenant';

    protected $fillable = [
        'definition_id', 'locale', 'label', 'help_text', 'placeholder',
    ];

    public function definition(): BelongsTo
    {
        return $this->belongsTo(CustomFieldDefinition::class, 'definition_id');
    }
}
