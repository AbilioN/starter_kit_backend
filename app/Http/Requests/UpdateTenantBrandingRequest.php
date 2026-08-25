<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantBrandingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'theme_primary_color' => 'nullable|string|regex:/^#[0-9a-fA-F]{6}$/',
            'theme_secondary_color' => 'nullable|string|regex:/^#[0-9a-fA-F]{6}$/',
            'theme_tertiary_color' => 'nullable|string|regex:/^#[0-9a-fA-F]{6}$/',
            // logo_path: set directly when the logo was already uploaded elsewhere.
            // logo: multipart file upload — takes precedence when both are present.
            'logo_path' => 'nullable|string|max:255',
            'logo' => 'nullable|image|max:2048',
        ];
    }
}
