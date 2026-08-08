<?php

namespace App\Application\UseCases\Template;

use App\Application\Services\PlaceholderResolverService;
use App\Application\Services\SystemEmailDefaults;
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
    ) {}

    /**
     * @param array<string, string> $promptValues
     * @return array{subject: string, body: string, is_html: bool}
     */
    public function execute(string $key, array $promptValues = []): array
    {
        $template = $this->templateRepository->findByKey($key);

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
}
