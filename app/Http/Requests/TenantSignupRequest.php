<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TenantSignupRequest extends FormRequest
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
                // "landlord.tenants": the tenants table lives on the landlord
                // connection, not whatever database.default happens to be
                // for a pre-tenant-resolution request like this one.
                'unique:landlord.tenants,subdomain',
            ],
            'plan_id' => 'nullable|uuid|exists:landlord.subscription_plans,id',
            'admin_email' => 'required|email',
            'admin_password' => 'required|string|min:8|confirmed',
        ];
    }
}
