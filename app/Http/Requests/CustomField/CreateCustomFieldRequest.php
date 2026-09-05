<?php

namespace App\Http\Requests\CustomField;

use App\Domain\CustomFields\CustomFieldHostRegistry;
use App\Domain\CustomFields\FieldTypeRegistry;
use App\Models\CustomFieldRoleRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a new field definition.
 *
 * **No `messages()` override, on purpose.** Nine FormRequests once carried
 * their own copy — six in English, two in Portuguese — and they are why the
 * API answered one session in two languages. Custom copy belongs in
 * `lang/<locale>/validation.php` under `custom`, in all four of pt/en/es/fr,
 * and only when it says something a rule cannot.
 *
 * The closed sets come from the registries rather than from a const array,
 * because they ARE the registries: a host that is not registered is a table
 * name assembled from nothing, and a type that is not registered is a storage
 * decision nobody can make.
 */
class CreateCustomFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Permission is enforced in the controller through
        // AuthorizeActionUseCase, the house pattern — this stays true so a
        // denial produces a 403 with a message rather than an opaque one.
        return true;
    }

    public function rules(): array
    {
        $hosts = app(CustomFieldHostRegistry::class);
        $types = app(FieldTypeRegistry::class);

        return [
            'host' => ['required', 'string', Rule::in($hosts->keys())],
            'field_type' => ['required', 'string', Rule::in($types->keys())],
            'is_filterable' => ['sometimes', 'boolean'],

            // At least one language. A field with no name is not a field, and
            // the catalogue would have nothing to fall back to.
            'labels' => ['required', 'array', 'min:1'],
            // Bounded by what the PLATFORM can render, never by the tenant's
            // own locales.enabled — enabled says what a tenant offers, which
            // is not the same as what exists.
            'labels.*' => ['required', 'array'],
            'labels.*.label' => ['required', 'string', 'max:120'],
            'labels.*.help_text' => ['sometimes', 'nullable', 'string', 'max:255'],
            'labels.*.placeholder' => ['sometimes', 'nullable', 'string', 'max:120'],

            'section' => ['sometimes', 'nullable', 'string', 'max:60'],
            'slot' => ['sometimes', 'nullable', 'string', 'max:60'],
            'position' => ['sometimes', 'integer', 'min:0', 'max:65535'],

            'icon' => ['sometimes', 'nullable', 'string', 'max:60'],
            // The same rule the plan form already uses for its tertiary
            // colour, so the two screens agree on what a colour is.
            'colour' => ['sometimes', 'nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'colour_dark' => ['sometimes', 'nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'font_size' => ['sometimes', 'integer', 'min:0', 'max:255'],

            'is_required' => ['sometimes', 'boolean'],
            'pattern' => ['sometimes', 'nullable', 'string', 'max:200'],

            'role_rules' => ['sometimes', 'array'],
            'role_rules.*' => ['array'],
            'role_rules.*.*' => ['string', 'max:36'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $this->validateLocales($validator);
            $this->validateSlotAndSection($validator);
            $this->validateFilterability($validator);
            $this->validatePattern($validator);
            $this->validateRoleRules($validator);
        });
    }

    private function validateLocales($validator): void
    {
        $available = config('app.available_locales', []);

        foreach (array_keys((array) $this->input('labels', [])) as $locale) {
            if (! in_array($locale, $available, true)) {
                $validator->errors()->add("labels.{$locale}", "Unsupported locale [{$locale}].");
            }
        }
    }

    private function validateSlotAndSection($validator): void
    {
        $host = app(CustomFieldHostRegistry::class)->get((string) $this->input('host'));

        if ($host === null) {
            return; // The `in:` rule already reported it.
        }

        $slot = $this->input('slot');

        if ($slot !== null && $slot !== '' && ! array_key_exists($slot, $host->slots())) {
            $validator->errors()->add('slot', 'Unknown slot for this entity.');
        }

        $section = $this->input('section');

        if ($section !== null && $section !== '' && ! array_key_exists($section, $host->sections())) {
            $validator->errors()->add('section', 'Unknown section for this entity.');
        }
    }

    private function validateFilterability($validator): void
    {
        if (! $this->boolean('is_filterable')) {
            return;
        }

        $type = app(FieldTypeRegistry::class)->get((string) $this->input('field_type'));

        // Refused rather than silently ignored. Filtering a free-text column
        // needs an index MySQL will not give without a prefix length (error
        // 1170), so promising it would sell the tenant a filter that scans.
        if ($type !== null && ! $type->canFilter()) {
            $validator->errors()->add('is_filterable', 'This field type cannot be filtered.');
        }
    }

    private function validatePattern($validator): void
    {
        $pattern = $this->input('pattern');

        if ($pattern === null || $pattern === '') {
            return;
        }

        // It must COMPILE before it is stored. A pattern that throws at match
        // time would 422 every subsequent write on that host, and the only way
        // out would be editing the row by hand.
        if (@preg_match('/'.str_replace('/', '\/', $pattern).'/', '') === false) {
            $validator->errors()->add('pattern', 'This is not a valid regular expression.');
        }
    }

    private function validateRoleRules($validator): void
    {
        foreach (array_keys((array) $this->input('role_rules', [])) as $rule) {
            if (! in_array($rule, CustomFieldRoleRule::RULES, true)) {
                $validator->errors()->add("role_rules.{$rule}", 'Unknown rule.');
            }
        }
    }
}
