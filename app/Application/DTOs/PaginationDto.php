<?php

namespace App\Application\DTOs;

class PaginationDto
{
    public function __construct(
        public readonly int $currentPage,
        public readonly int $perPage,
        public readonly int $total,
        public readonly int $lastPage,
        public readonly ?int $from,
        public readonly ?int $to
    ) {}

    /**
     * Converte o DTO em array
     */
    public function toArray(): array
    {
        return [
            'current_page' => $this->currentPage,
            'per_page' => $this->perPage,
            'total' => $this->total,
            'last_page' => $this->lastPage,
            'from' => $this->from,
            'to' => $this->to
        ];
    }

    /**
     * Cria DTO a partir de array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            currentPage: $data['current_page'],
            perPage: $data['per_page'],
            total: $data['total'],
            lastPage: $data['last_page'],
            from: $data['from'] ?? null,
            to: $data['to'] ?? null
        );
    }
} 