<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            // type is immutable after creation (mirrors slug on
            // SubscriptionPlan) — changing it would leave stale
            // body/positions data in the wrong shape.
            'body_format' => 'sometimes|string|in:text,html,positions',
            'body' => 'nullable|string',
            'subject' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
            'options' => 'sometimes|array',
            'options.sender' => 'nullable|email',
            'options.locked' => 'nullable|boolean',
            'locale' => 'nullable|string|max:10',
        ];
    }
}
