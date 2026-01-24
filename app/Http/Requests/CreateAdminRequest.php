<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization is handled in the controller
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:admins,email|max:255',
            'password' => 'required|string|min:8|confirmed',
            'is_active' => 'sometimes|boolean',
            'role_id' => 'sometimes|integer|exists:roles,id'
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'The name field is required',
            'email.required' => 'The email field is required',
            'email.email' => 'The email must be a valid email address',
            'email.unique' => 'This email address is already registered',
            'password.required' => 'The password field is required',
            'password.min' => 'The password must be at least 8 characters',
            'password.confirmed' => 'The password confirmation does not match',
            'role_id.exists' => 'The selected role does not exist'
        ];
    }
}

