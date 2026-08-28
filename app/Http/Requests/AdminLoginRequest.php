<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdminLoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    /*
     * No messages() override on purpose. Every message this class used to carry
     * restated the rule it came from ("Email is required"), which is what
     * Laravel's own translated defaults already say — in four languages since
     * roadmap 5.8, where these hardcoded strings said it in one. Two of these
     * classes said it in Portuguese and six in English, so the API answered a
     * single request in two languages depending on which endpoint you hit.
     *
     * Override this again only for a message that carries product meaning a
     * validation rule cannot express (see PublicTenantSignupRequest), and put
     * the text in lang/<locale>/validation.php under `custom`, never inline.
     */
}
