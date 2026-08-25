<?php

namespace App\Application\UseCases\Template;

use App\Application\Services\PlaceholderResolverService;
use App\Application\Services\SystemEmailDefaults;
use App\Domain\Entities\Template;
use App\Domain\Repositories\TemplateRepositoryInterface;

/**
 * Renders one of the well-known system email slots (welcome_email,
 * password_reset_email, password_changed_email, ...) against the CURRENT
 * tenant connection. Always returns something to send: falls back to
 * SystemEmailDefaults when the tenant has no template for that slot
 * (shouldn't normally happen — SystemTemplateSeeder seeds all of them at
 * provisioning — but a tenant can delete/deactivate the row) or there's no
 * tenant context at all (tests, console).
 *
 * Multi-language: a slot may be authored in as many languages as the tenant
 * runs. $preferredLocale is the RECIPIENT's own choice, not a decision this
 * use case makes — ResolveTemplateLocaleUseCase turns it into one of the
 * languages that actually exist. Passing null is normal (a recipient who
 * never picked one) and still sends, in the tenant's default.
 *
 * Deliberately does not thread a recordId/MergeContext through: a system
 * notification isn't "about" a CRM record the way a real template send is.
 * $promptValues — generated fresh per send, never persisted (recipient
 * name, a one-time reset URL) — is exactly the seam
 * PlaceholderResolverService already exposes for this ({prompt:key}).
 * {company} still resolves from the tenant automatically either way.
 */
class RenderSystemTemplateUseCase
{
    public function __construct(
        private TemplateRepositoryInterface $templateRepository,
        private PlaceholderResolverService $resolver,
        private ResolveTemplateLocaleUseCase $resolveLocale,
    ) {}

    /**
     * @param  array<string, string>  $promptValues
     * @return array{subject: string, body: string, is_html: bool}
     */
    public function execute(string $key, array $promptValues = [], ?string $preferredLocale = null): array
    {
        $template = $this->pickTranslation($key, $preferredLocale);

        if ($template && $template->isActive) {
            return [
                'subject' => $this->resolver->resolve($template->subject ?? $template->name, promptValues: $promptValues),
                'body' => $this->resolver->resolve($template->body ?? '', promptValues: $promptValues),
                'is_html' => $template->bodyFormat === 'html',
            ];
        }

        $default = SystemEmailDefaults::for($key);

        return [
            'subject' => $this->resolver->resolve($default['subject'], promptValues: $promptValues),
            'body' => $this->resolver->resolve($default['body'], promptValues: $promptValues),
            'is_html' => true,
        ];
    }

    /**
     * Only ACTIVE translations are candidates. Deactivating the German
     * welcome e-mail has to mean a German recipient gets another language,
     * not that they get the inactive German one anyway — otherwise the
     * is_active switch would do nothing for a multilingual slot.
     */
    private function pickTranslation(string $key, ?string $preferredLocale): ?Template
    {
        $translations = array_values(array_filter(
            $this->templateRepository->findAllByKey($key),
            fn (Template $template) => $template->isActive,
        ));

        if ($translations === []) {
            return null;
        }

        $locale = $this->resolveLocale->execute(
            available: array_map(fn (Template $template) => $template->locale, $translations),
            preferred: $preferredLocale,
        );

        foreach ($translations as $template) {
            if ($template->locale === $locale) {
                return $template;
            }
        }

        return $translations[0];
    }
}
