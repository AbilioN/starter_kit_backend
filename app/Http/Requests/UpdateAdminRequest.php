<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization is handled in the controller
    }

    public function rules(): array
    {
        $adminId = $this->input('id');
        
        return [
            'id' => 'required|integer|exists:admins,id',
            'name' => 'sometimes|string|max:255',
            'email' => [
                'sometimes',
                'email',
                'max:255',
                Rule::unique('admins', 'email')->ignore($adminId)
            ],
            'password' => 'sometimes|string|min:8|confirmed',
            'is_active' => 'sometimes|boolean'
        ];
    }

    public function messages(): array
    {
        return [
            'id.required' => 'The admin ID is required',
            'id.exists' => 'The admin does not exist',
            'email.email' => 'The email must be a valid email address',
            'email.unique' => 'This email address is already registered',
            'password.min' => 'The password must be at least 8 characters',
            'password.confirmed' => 'The password confirmation does not match'
        ];
    }
}

