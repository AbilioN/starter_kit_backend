<?php

namespace App\Application\DTOs\File;

class FileDto
{
    public function __construct(
        public readonly int $id,
        public readonly string $original_name,
        public readonly string $mime_type,
        public readonly int $size,
        public readonly string $size_human,
        public readonly ?string $folder,
        public readonly bool $is_public,
        public readonly string $url,
        public readonly ?array $meta,
        public readonly ?string $created_at,
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'size_human' => $this->size_human,
            'folder' => $this->folder,
            'is_public' => $this->is_public,
            'url' => $this->url,
            'meta' => $this->meta,
            'created_at' => $this->created_at,
        ];
    }
}
