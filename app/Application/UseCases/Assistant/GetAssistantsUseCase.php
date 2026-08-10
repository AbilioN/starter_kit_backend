<?php

namespace App\Application\UseCases\Assistant;

use App\Domain\Repositories\AssistantRepositoryInterface;

class GetAssistantsUseCase
{
    public function __construct(
        private AssistantRepositoryInterface $assistantRepository,
    ) {}

    /**
     * @return array<int, \App\Domain\Entities\Assistant>
     */
    public function execute(): array
    {
        return $this->assistantRepository->getAllActive();
    }
}
