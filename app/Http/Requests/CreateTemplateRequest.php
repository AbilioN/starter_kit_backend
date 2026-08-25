<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CreateTemplateRequest extends FormRequest
{
    /**
     * Which body_format values each type actually allows — pdf is the only
     * type with two authoring models (spec §4: HTML document vs. entries
     * positioned over a background).
     */
    private const VALID_BODY_FORMATS = [
        'text_email' => ['text'],
        'sms' => ['text'],
        'ai_prompt' => ['text'],
        'html_email' => ['html'],
        'pdf' => ['html', 'positions'],
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:'.implode(',', array_keys(self::VALID_BODY_FORMATS)),
            'body_format' => 'required|string|in:text,html,positions',
            'body' => 'nullable|string',
            'subject' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'options' => 'nullable|array',
            'options.sender' => 'nullable|email',
            'options.locked' => 'nullable|boolean',
            // Which language this body is written in. Omitted means the
            // tenant's default — a monolingual tenant never sends it.
            'locale' => 'nullable|string|max:10',
            // Present only when adding a language to an existing template.
            'translation_group_id' => 'nullable|uuid',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $type = $this->input('type');
            $bodyFormat = $this->input('body_format');

            if ($type && $bodyFormat && ! in_array($bodyFormat, self::VALID_BODY_FORMATS[$type] ?? [], true)) {
                $allowed = implode(' or ', self::VALID_BODY_FORMATS[$type] ?? []);
                $validator->errors()->add('body_format', "A '{$type}' template must use body_format: {$allowed}.");
            }
        });
    }
}
