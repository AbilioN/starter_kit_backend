<?php

namespace App\Infrastructure\Services;

use App\Domain\Services\MergeContextInterface;

/**
 * Fixed catalogue and fake values — no real business entity is wired in
 * yet. Every renderer, the field picker in the editor, and the preview
 * endpoint all work against this. When a real entity (User, Chat, whatever
 * ends up being merged into a template) lands, implement
 * MergeContextInterface once for it and nothing else in the module needs
 * to change.
 */
class StubMergeContext implements MergeContextInterface
{
    public function fields(): array
    {
        return [
            ['key' => 'first_name', 'label' => 'First Name'],
            ['key' => 'last_name', 'label' => 'Last Name'],
            ['key' => 'email', 'label' => 'Email'],
            ['key' => 'zip', 'label' => 'ZIP Code'],
            ['key' => 'date', 'label' => 'Date'],
        ];
    }

    public function values(int|string $recordId): array
    {
        return [
            '{first_name}' => 'Jean',
            '{last_name}' => 'Sample',
            '{email}' => 'jean.sample@example.com',
            '{zip}' => '75001',
            '{date}' => now()->format('d/m/Y'),
        ];
    }
}
