<?php

namespace App\Domain\Entities;

use App\Application\DTOs\File\FileDto;
use DateTime;

class File
{
    public function __construct(
        public readonly string $id,
        public readonly string $originalName,
        public readonly string $storedName,
        public readonly string $disk,
        public readonly string $path,
        public readonly string $mimeType,
        public readonly int $size,
        public readonly ?string $uploadableType,
        public readonly int|string|null $uploadableId,
        public readonly ?string $folder,
        public readonly bool $isPublic,
        public readonly ?array $meta,
        public readonly ?DateTime $createdAt,
        public readonly ?DateTime $updatedAt,
    ) {}

    public function toDto(string $url): FileDto
    {
        return new FileDto(
            id: $this->id,
            original_name: $this->originalName,
            mime_type: $this->mimeType,
            size: $this->size,
            size_human: $this->humanSize(),
            folder: $this->folder,
            is_public: $this->isPublic,
            url: $url,
            meta: $this->meta,
            created_at: $this->createdAt?->format('Y-m-d H:i:s'),
        );
    }

    private function humanSize(): string
    {
        $bytes = $this->size;
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        if ($bytes < 1073741824) return round($bytes / 1048576, 1) . ' MB';
        return round($bytes / 1073741824, 2) . ' GB';
    }
}
