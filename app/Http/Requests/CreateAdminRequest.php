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
            'role_id' => 'sometimes|string|exists:roles,id'
        ];
    }

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

