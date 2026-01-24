<?php

namespace App\Application\UseCases\Admin;

use App\Domain\Repositories\AdminRepositoryInterface;

class UpdateAdminUseCase
{
    public function __construct(
        private AdminRepositoryInterface $adminRepository
    ) {}

    public function execute(
        int $id,
        ?string $name = null,
        ?string $email = null,
        ?string $password = null,
        ?bool $isActive = null
    ): array {
        $admin = $this->adminRepository->findById($id);
        
        if (!$admin) {
            throw new \Exception("Admin not found");
        }
        
        $updatedAdmin = $this->adminRepository->update($id, $name, $email, $password, $isActive);
        
        return $updatedAdmin->toDto()->toArray();
    }
}

