<?php

namespace App\Domain\Services;

use Illuminate\Http\UploadedFile;

interface IconResizingServiceInterface
{
    /**
     * Resizes $file into small/medium/large square variants, stores them
     * under $folder on the `public` disk, and returns their relative paths
     * (e.g. ['small' => 'subscription-plan-icons/{id}/small.png', ...]).
     */
    public function generateSizes(UploadedFile $file, string $folder): array;
}
