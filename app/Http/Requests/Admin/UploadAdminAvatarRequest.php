<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UploadAdminAvatarRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Qualquer admin autenticado pode ter foto — a rota já está sob
        // auth:sanctum + admin.auth, e cada um só altera o próprio avatar
        // (o id vem de $request->user(), nunca do corpo).
        return true;
    }

    public function rules(): array
    {
        return [
            // `mimes` explícito e não apenas `image`: a regra `image` do Laravel
            // aceita SVG, e o ficheiro é servido a partir da própria origem do
            // painel em /storage/... — um SVG com <script> seria XSS armazenado
            // contra qualquer admin que visse o avatar.
            // max:2048 = 2 MB, igual ao logo do tenant (UpdateTenantBrandingRequest).
            'avatar' => ['required', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
        ];
    }
}
