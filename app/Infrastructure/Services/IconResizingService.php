<?php

namespace App\Infrastructure\Services;

use App\Domain\Services\IconResizingServiceInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;

class IconResizingService implements IconResizingServiceInterface
{
    /**
     * Square pixel dimensions for each generated variant. GD driver (no
     * Imagick dependency) - matches the `gd` extension already configured
     * with JPEG support in the Dockerfile.
     */
    private const SIZES = [
        'small' => 32,
        'medium' => 128,
        'large' => 512,
    ];

    public function generateSizes(UploadedFile $file, string $folder): array
    {
        $manager = ImageManager::gd();
        $source = $manager->read($file->getRealPath());
        $groupId = (string) Str::uuid();

        $paths = [];

        foreach (self::SIZES as $size => $pixels) {
            $relativePath = "{$folder}/{$groupId}/{$size}.png";
            $absolutePath = Storage::disk('public')->path($relativePath);

            Storage::disk('public')->makeDirectory("{$folder}/{$groupId}");

            (clone $source)->cover($pixels, $pixels)->toPng()->save($absolutePath);

            $paths[$size] = $relativePath;
        }

        return $paths;
    }
}
