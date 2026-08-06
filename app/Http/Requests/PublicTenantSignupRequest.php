<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PublicTenantSignupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'subdomain' => [
                'required',
                'string',
                'max:63',
                'regex:/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/',
                'unique:landlord.tenants,subdomain',
            ],
            'plan_id' => [
                'nullable',
                'uuid',
                // Critical: also requires is_public=true, not just existence.
                // Without this, a visitor could self-assign any guessed/
                // enumerated private plan's UUID - "private" only means
                // anything if the public signup path can't reach it.
                Rule::exists('landlord.subscription_plans', 'id')->where('is_public', true)->where('is_active', true),
            ],
            'admin_email' => 'required|email',
            'admin_password' => 'required|string|min:8|confirmed',
        ];
    }

    public function messages(): array
    {
        return [
            'plan_id.exists' => 'The selected plan is not available for self-service signup.',
        ];
    }
}
