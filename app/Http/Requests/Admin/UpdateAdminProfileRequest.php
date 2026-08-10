<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAdminProfileRequest extends FormRequest
{
    /**
     * `PATCH /api/admin/me` é usado por todos os admins, logo não pode ficar sob
     * o middleware `tenant.owner`. O gate aqui é por CAMPO: só o tenant owner
     * pode definir — ou limpar — o endereço de notificação crítica.
     *
     * `has()` devolve true para um `null` explícito, o que é o comportamento
     * desejado: apagar o endereço também é privilégio do owner.
     *
     * Falhar aqui produz um 403 real (AuthorizationException), não um 422 —
     * semanticamente é falta de permissão, não erro de validação.
     */
    public function authorize(): bool
    {
        return ! $this->has('notification_email')
            || (bool) $this->user()?->is_tenant_owner;
    }

    public function rules(): array
    {
        return [
            // `sometimes|required` em vez de `required`: permite ao owner gravar
            // só o notification_email, mas continua rejeitando um nome vazio
            // quando o campo é enviado.
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'notification_email' => ['sometimes', 'nullable', 'email:rfc', 'max:255'],
        ];
    }
}
