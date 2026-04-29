<?php

namespace App\Infrastructure\Services;

use App\Domain\Services\StorageServiceInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StorageService implements StorageServiceInterface
{
    public function store(UploadedFile $file, string $folder, string $disk = 'local'): array
    {
        $extension = $file->getClientOriginalExtension();
        $storedName = Str::uuid() . '.' . $extension;
        $path = $file->storeAs($folder, $storedName, $disk);

        return [
            'original_name' => $file->getClientOriginalName(),
            'stored_name' => $storedName,
            'disk' => $disk,
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
        ];
    }

    public function delete(string $path, string $disk = 'local'): void
    {
        Storage::disk($disk)->delete($path);
    }

    public function url(string $path, string $disk = 'local'): string
    {
        if ($disk === 's3') {
            return Storage::disk('s3')->url($path);
        }

        return url('/api/files/serve/' . base64_encode($path));
    }

    public function temporaryUrl(string $path, string $disk = 's3', int $minutes = 60): string
    {
        return Storage::disk($disk)->temporaryUrl($path, now()->addMinutes($minutes));
    }
}
