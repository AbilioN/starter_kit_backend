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
            'logo_path' => 'nullable|string|max:255',
        ];
    }
}
