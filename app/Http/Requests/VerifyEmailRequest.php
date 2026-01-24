<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerifyEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => 'required|email',
            'code' => 'required|string|size:6',
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'O email é obrigatório.',
            'email.email' => 'O email deve ser válido.',
            'code.required' => 'O código de verificação é obrigatório.',
            'code.string' => 'O código deve ser uma string.',
            'code.size' => 'O código deve ter exatamente 6 dígitos.',
        ];
    }
} 