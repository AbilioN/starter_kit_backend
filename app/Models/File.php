<?php

namespace App\Models;

use App\Domain\Entities\File as FileEntity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class File extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'original_name',
        'stored_name',
        'disk',
        'path',
        'mime_type',
        'size',
        'uploadable_type',
        'uploadable_id',
        'folder',
        'is_public',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'size' => 'integer',
            'meta' => 'array',
        ];
    }

    public function uploadable()
    {
        return $this->morphTo();
    }

    public function toEntity(): FileEntity
    {
        return new FileEntity(
            id: $this->id,
            originalName: $this->original_name,
            storedName: $this->stored_name,
            disk: $this->disk,
            path: $this->path,
            mimeType: $this->mime_type,
            size: $this->size,
            uploadableType: $this->uploadable_type,
            uploadableId: $this->uploadable_id,
            folder: $this->folder,
            isPublic: $this->is_public,
            meta: $this->meta,
            createdAt: $this->created_at,
            updatedAt: $this->updated_at,
        );
    }
}
