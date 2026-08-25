<?php

namespace App\Application\UseCases\Template;

use App\Helpers\Settings;

/**
 * Decides which language a template is sent in.
 *
 * A tenant may run one language or ten, so this is a cascade, not a setting:
 *
 *   1. what the recipient asked for  (users/admins.locale — null when never asked)
 *   2. the tenant's default          (settings `locales.default`)
 *   3. whatever translation exists   (first of $available)
 *
 * Step 3 is the one that matters in practice. A tenant enables French and
 * German, someone writes only the German welcome e-mail, and a French user
 * signs up: preferring "no e-mail" over "an e-mail in the wrong language"
 * would be a silent failure at exactly the moment a new user is waiting for
 * a link. Sending German is worse than sending French and better than
 * sending nothing.
 *
 * A preference is honoured only if a translation actually exists for it —
 * `locales.enabled` says what the tenant OFFERS, which is not the same as
 * what has been written yet, and only the second one can be sent.
 */
class ResolveTemplateLocaleUseCase
{
    /**
     * @param  array<int, string>  $available  locales that have an authored translation
     * @param  string|null  $preferred  the recipient's own locale, when known
     */
    public function execute(array $available, ?string $preferred = null): ?string
    {
        if ($available === []) {
            return null;
        }

        foreach ([$preferred, $this->tenantDefault()] as $candidate) {
            $match = $this->match($candidate, $available);

            if ($match !== null) {
                return $match;
            }
        }

        return $available[0];
    }

    /**
     * The languages this tenant offers. Not the same as the languages its
     * templates are written in — this is what the authoring UI shows as tabs,
     * including empty ones still to be filled in.
     *
     * @return array<int, string>
     */
    public function enabledLocales(): array
    {
        $raw = Settings::get('locales.enabled');

        $locales = is_string($raw) ? array_map('trim', explode(',', $raw)) : (array) $raw;
        $locales = array_values(array_filter($locales, fn ($locale) => $locale !== '' && $locale !== null));

        // A tenant that never configured languages still runs one, and the
        // authoring UI needs a tab to draw.
        return $locales !== [] ? $locales : [$this->tenantDefault()];
    }

    public function tenantDefault(): string
    {
        return (string) (Settings::get('locales.default') ?: config('app.locale', 'en'));
    }

    /**
     * Exact match first, then the base language: a recipient who asked for
     * 'pt-BR' should get 'pt' rather than falling through to the tenant
     * default, and one who asked for 'pt' takes 'pt-BR' over nothing.
     *
     * @param  array<int, string>  $available
     */
    private function match(?string $candidate, array $available): ?string
    {
        if ($candidate === null || $candidate === '') {
            return null;
        }

        if (in_array($candidate, $available, true)) {
            return $candidate;
        }

        $base = strtolower(explode('-', $candidate)[0]);

        foreach ($available as $locale) {
            if (strtolower(explode('-', $locale)[0]) === $base) {
                return $locale;
            }
        }

        return null;
    }
}
