<?php

namespace App\Http\Requests\Admin;

use App\Application\Services\TenantLocales;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Long enough for a real set of house rules; anything longer belongs in a
     * document, which is only read when it is relevant instead of on every
     * message. Mirrored by the panel's counter and by the hard ceiling in
     * ProcessOpenAIRequest, which is the one that cannot be bypassed.
     */
    public const AI_INSTRUCTIONS_MAX = 4000;

    /** Both halves of the split: public-facing, and staff-only. */
    public const AI_INSTRUCTION_KEYS = ['ai.instructions', 'ai.instructions_internal'];

    public function rules(): array
    {
        $rules = ['value' => self::rulesForKey((string) $this->route('key'))];

        if ($this->route('key') === 'locales.enabled') {
            // Bounded by what the PRODUCT can render. A tenant cannot offer a
            // language there are no translations for.
            $rules['value.*'] = ['string', Rule::in(config('app.available_locales', []))];
        }

        return $rules;
    }

    /**
     * @return array<int, string>
     */
    public static function rulesForKey(string $key): array
    {
        // The languages this organisation operates in. Validated because a
        // bad value here is not a bad screen — `locales.enabled` decides which
        // tabs an author is asked to fill, and an entry the product cannot
        // render would silently swallow everything typed into it.
        if ($key === 'locales.enabled') {
            return ['required', 'array', 'min:1'];
        }

        if ($key === 'locales.default') {
            // Must be OFFERED, not merely renderable: a default outside the
            // enabled set marks a tab that is never drawn.
            return [
                'required',
                'string',
                Rule::in(app(TenantLocales::class)->enabled()),
            ];
        }

        if (! in_array($key, self::AI_INSTRUCTION_KEYS, true)) {
            return ['required'];
        }

        // `present|nullable`, NOT `required`. Laravel reads '' as empty, so
        // `required` made these the one kind of setting that could be written
        // once and never cleared — a tenant that decided the assistant should
        // stop following house rules had no way to say so, and there is no
        // DELETE route for a setting.
        //
        // Capped because these are concatenated into the system prompt of
        // EVERY message; nothing here capped anything before, which was
        // harmless while settings were flags and names.
        return ['present', 'nullable', 'string', 'max:'.self::AI_INSTRUCTIONS_MAX];
    }
}
