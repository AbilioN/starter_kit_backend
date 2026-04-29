<?php

namespace App\Domain\Services;

use Illuminate\Http\UploadedFile;

interface StorageServiceInterface
{
    public function store(UploadedFile $file, string $folder, string $disk = 'local'): array;
    public function delete(string $path, string $disk = 'local'): void;
    public function url(string $path, string $disk = 'local'): string;
    public function temporaryUrl(string $path, string $disk = 's3', int $minutes = 60): string;
}
