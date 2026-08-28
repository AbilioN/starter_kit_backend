<?php

namespace App\Http\Middleware;

use App\Application\UseCases\Template\ResolveTemplateLocaleUseCase;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Decides what language this request answers in (roadmap 5.8).
 *
 * The panel has shipped pt/en/es/fr since Sprint 0 while the API answered in
 * English only, so a Portuguese admin got Portuguese labels with English
 * validation errors under them.
 *
 * The cascade:
 *
 *  1. **What this person chose** (`users`/`admins`.locale). An explicit choice
 *     beats every inference; null means they never said, not that they picked
 *     the default.
 *  2. **The tenant's default** (`locales.default`).
 *  3. **Accept-Language**, bounded by the locales this application actually
 *     ships translations for.
 *  4. `config('app.locale')`.
 *
 * Step 2 sits ahead of step 3 deliberately, and it is the part worth
 * explaining. The browser header is a statement about the person's *device*,
 * not about the organisation paying for the account: most browsers send
 * `en-US` regardless of where they are, so consulting it first would answer a
 * Brazilian tenant in English because someone installed an English Windows.
 * In a BtoB product the organisation decides what language its own back office
 * speaks — which is why `locales.default` is a tenant setting in the first
 * place.
 *
 * That leaves step 3 reachable only when there is no tenant to ask: signup,
 * public pricing, the health probes. That is exactly where it belongs, and it
 * is the whole of its usefulness.
 *
 * Runs after IdentifyTenant (it reads tenant settings) and after authentication
 * (it reads the person's own column). Registered on the whole `api` group
 * rather than route by route, for the same reason ImpersonationGuard is: a
 * route group added later must not silently lose it.
 */
class SetLocale
{
    public function __construct(
        private ResolveTemplateLocaleUseCase $locales,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolve($request);

        if ($locale !== null) {
            app()->setLocale($locale);
        }

        return $next($request);
    }

    private function resolve(Request $request): ?string
    {
        $chosen = $request->user()?->locale;

        if (is_string($chosen) && $chosen !== '') {
            return $chosen;
        }

        $tenantDefault = $this->tenantDefault();

        if ($tenantDefault !== null) {
            return $tenantDefault;
        }

        // No tenant: the header is the only evidence there is. Bounded by what
        // this application has translations for — getPreferredLanguage()
        // returns the FIRST candidate when nothing matches rather than null, so
        // the header has to be checked for a real mention before it counts.
        $available = $this->availableLocales();
        $preferred = $request->getPreferredLanguage($available);

        if ($preferred !== null && $this->headerMentions($request, $preferred)) {
            return $preferred;
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function availableLocales(): array
    {
        $available = config('app.available_locales', []);

        return is_array($available) && $available !== []
            ? array_values(array_filter($available, 'is_string'))
            : [(string) config('app.locale')];
    }

    /**
     * `currentTenant` is what IdentifyTenant binds once it has resolved one, so
     * asking the container is an exact answer to "is there an organisation to
     * defer to here?" — where catching an exception from the settings lookup
     * would also swallow a genuinely broken tenant database and call it
     * "public".
     */
    private function tenantDefault(): ?string
    {
        if (! app()->bound('currentTenant')) {
            return null;
        }

        try {
            return $this->locales->tenantDefault();
        } catch (\Throwable) {
            // The tenant's own database is unreachable. Which language to
            // answer in is not the thing to fail the request over — and the
            // request is almost certainly failing for a better reason already.
            return null;
        }
    }

    /**
     * Guards against getPreferredLanguage()'s fallback: the returned locale
     * only counts when the header actually asked for that language.
     */
    private function headerMentions(Request $request, string $locale): bool
    {
        foreach ($request->getLanguages() as $language) {
            if (str_starts_with(str_replace('_', '-', strtolower($language)), strtolower($locale))) {
                return true;
            }
        }

        return false;
    }
}
