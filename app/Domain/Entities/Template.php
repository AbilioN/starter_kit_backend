<?php

namespace App\Domain\Entities;

use DateTime;

class Template
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $key,
        /** BCP-47-ish tag ('pt', 'en', 'pt-BR'). Which language THIS row is written in. */
        public readonly ?string $locale,
        /** Ties this row to the same template in other languages. */
        public readonly ?string $translationGroupId,
        public readonly string $name,
        public readonly string $type,
        public readonly string $bodyFormat,
        public readonly ?string $body,
        public readonly ?string $subject,
        public readonly ?string $description,
        public readonly bool $isActive,
        public readonly array $options = [],
        public readonly ?DateTime $createdAt = null,
        public readonly ?DateTime $updatedAt = null,
    ) {}
}
