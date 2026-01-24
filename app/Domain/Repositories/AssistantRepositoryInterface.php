<?php

namespace App\Domain\Repositories;

use App\Domain\Entities\Assistant;

interface AssistantRepositoryInterface
{
    /**
     * Find an assistant by ID
     */
    public function findById(int $id): ?Assistant;

    /**
     * Find an active assistant by ID
     */
    public function findActiveById(int $id): ?Assistant;

    /**
     * Get all active assistants
     */
    public function getAllActive(): array;
}
