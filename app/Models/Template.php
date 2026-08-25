<?php

namespace App\Models;

use App\Domain\Entities\Template as TemplateEntity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Template extends Model
{
    use HasUuids, HasFactory;

    protected $connection = 'tenant';

    protected $fillable = [
        'key',
        'locale',
        'translation_group_id',
        'name',
        'type',
        'body_format',
        'body',
        'subject',
        'description',
        'is_active',
        'options',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'options' => 'array',
        ];
    }

    /**
     * Background PDF pages reuse the existing polymorphic `files` table —
     * no dedicated table for template attachments. Page order isn't a DB
     * column (avoids a JSON-path query that isn't portable across the
     * sqlite/mysql split this app already runs on) — sort by
     * `meta['sort']` in PHP after fetching, see TemplateRepository.
     */
    public function backgroundFiles(): MorphMany
    {
        return $this->morphMany(File::class, 'uploadable')->where('folder', 'pdf_background');
    }

    public function toEntity(): TemplateEntity
    {
        return new TemplateEntity(
            id: $this->id,
            key: $this->key,
            locale: $this->locale,
            translationGroupId: $this->translation_group_id,
            name: $this->name,
            type: $this->type,
            bodyFormat: $this->body_format,
            body: $this->body,
            subject: $this->subject,
            description: $this->description,
            isActive: $this->is_active,
            options: $this->options ?? [],
            createdAt: $this->created_at,
            updatedAt: $this->updated_at,
        );
    }
}
