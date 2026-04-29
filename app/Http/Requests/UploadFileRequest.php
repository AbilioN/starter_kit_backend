<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => 'required|file|max:102400', // 100MB max
            'folder' => 'nullable|string|max:100|alpha_dash',
            'disk' => 'nullable|in:local,s3',
            'is_public' => 'nullable|boolean',
            'meta' => 'nullable|array',
        ];
    }
}
