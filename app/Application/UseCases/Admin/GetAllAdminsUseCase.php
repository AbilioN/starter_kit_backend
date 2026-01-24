<?php

namespace App\Application\UseCases\Admin;

use App\Domain\Repositories\AdminRepositoryInterface;

class GetAllAdminsUseCase
{
    public function __construct(
        private AdminRepositoryInterface $adminRepository
    ) {}

    public function execute(): array
    {
        $admins = $this->adminRepository->findAll();
        
        return array_map(function ($admin) {
            return $admin->toDto()->toArray();
        }, $admins);
    }

    public function executePaginated(
        int $page = 1, 
        int $perPage = 15,
        ?string $search = null,
        ?bool $isActive = null
    ): array {
        $result = $this->adminRepository->findAllPaginated($page, $perPage, $search, $isActive);
        
        // Converter entidades para DTOs
        $result['data'] = array_map(function ($admin) {
            return $admin->toDto()->toArray();
        }, $result['data']);
        
        return $result;
    }
}

