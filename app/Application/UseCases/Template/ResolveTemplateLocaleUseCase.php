<?php

namespace App\Application\UseCases\Template;

use App\Application\Services\TenantLocales;

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
     * Optional, and constructed on demand when absent.
     *
     * The container injects it normally. But this class is also subclassed
     * anonymously in a unit test that stubs `tenantDefault()` to keep the
     * cascade real without a database, and a REQUIRED dependency broke that —
     * seven tests, for a collaborator that reads settings and holds no state
     * of its own, so building one costs nothing.
     */
    public function __construct(private ?TenantLocales $locales = null) {}

    private function locales(): TenantLocales
    {
        return $this->locales ??= new TenantLocales();
    }

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
     * The languages this tenant offers.
     *
     * Delegated to TenantLocales, which is the same question templates, custom
     * fields and the language switcher all ask. Kept here as a passthrough so
     * the existing call sites do not all have to change at once.
     *
     * @return array<int, string>
     */
    public function enabledLocales(): array
    {
        return $this->locales()->enabled();
    }

    public function tenantDefault(): string
    {
        return $this->locales()->default();
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
