<?php

namespace App\Application\Services;

use App\Helpers\Settings;

/**
 * Which languages this organisation operates in.
 *
 * Extracted from ResolveTemplateLocaleUseCase, which had grown two jobs: the
 * template half decides which language one message is *sent* in given the
 * translations somebody actually wrote, and this half answers a question three
 * other modules also ask — templates, custom fields and the language switcher.
 * Leaving it behind a template-shaped name meant the middleware and the field
 * catalogue were both reaching into "the template use case", which reads as an
 * accident rather than a decision.
 *
 * ## Two lists, and they answer different questions
 *
 * `config('app.available_locales')` is what the PRODUCT can render — the
 * directories under `lang/`. `locales.enabled` is what this ORGANISATION
 * chooses to publish in, always a subset.
 *
 * The rule that follows, and it has to be decided before a tenant first turns
 * a language off: **disabling a locale hides its tab; it never deletes a
 * translation.** So this class bounds what the interface ASKS FOR, while
 * validation of what may be STORED stays bound to the platform list — a tenant
 * that drops French must not have its existing French labels rejected on the
 * next save, or silently destroyed. Re-enabling brings the text back.
 */
class TenantLocales
{
    /**
     * The languages this tenant offers, in the tenant's own order.
     *
     * Intersected with the platform list, because a setting can outlive a
     * translation directory: without this a tenant carrying a stale locale
     * gets an authoring tab for a language nothing can render, and every
     * string in it falls back silently.
     *
     * Never empty — a tenant that never configured languages still runs one,
     * and the authoring UI needs a tab to draw.
     *
     * @return array<int, string>
     */
    public function enabled(): array
    {
        $raw = Settings::get('locales.enabled');

        $locales = is_string($raw) ? array_map('trim', explode(',', $raw)) : (array) $raw;
        $locales = array_values(array_filter(
            $locales,
            fn ($locale) => is_string($locale) && $locale !== '',
        ));

        $locales = array_values(array_intersect($locales, $this->available()));

        return $locales !== [] ? $locales : [$this->default()];
    }

    /**
     * The language a screen opens in, and the one a template falls back to.
     *
     * Guaranteed to be one the product can render. NOT guaranteed to be in
     * `enabled()` — asking enabled() for it would recurse — so callers that
     * need "the default, as offered" should use defaultAmongEnabled().
     */
    public function default(): string
    {
        $default = (string) (Settings::get('locales.default') ?: config('app.locale', 'en'));

        return in_array($default, $this->available(), true)
            ? $default
            : (string) config('app.fallback_locale', 'en');
    }

    /**
     * The default, guaranteed to be one of the offered languages.
     *
     * A default outside the enabled set would have the authoring UI mark a tab
     * that is not drawn, which is the sort of small lie that costs an hour to
     * diagnose.
     */
    public function defaultAmongEnabled(): string
    {
        $enabled = $this->enabled();
        $default = $this->default();

        return in_array($default, $enabled, true) ? $default : $enabled[0];
    }

    /** What the product can render at all. @return array<int, string> */
    public function available(): array
    {
        return (array) config('app.available_locales', ['en']);
    }

    /**
     * The shape every screen that authors translated content receives.
     *
     * One payload, so the three modules cannot drift on what "the tenant's
     * languages" means — which they already had, with templates honouring the
     * tenant list and custom fields offering all four.
     *
     * @return array{enabled: array<int, string>, default: string, available: array<int, string>}
     */
    public function forAuthoring(): array
    {
        return [
            'enabled' => $this->enabled(),
            'default' => $this->defaultAmongEnabled(),
            // Sent too, because it is what a WRITE is validated against: the
            // editor asks for the enabled ones and must still render a tab for
            // a translation authored before a locale was switched off.
            'available' => $this->available(),
        ];
    }
}
