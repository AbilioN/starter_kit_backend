<?php

namespace App\Application\UseCases\Admin;

use App\Domain\Repositories\AdminRepositoryInterface;

class DeleteAdminUseCase
{
    public function __construct(
        private AdminRepositoryInterface $adminRepository
    ) {}

    public function execute(int $id): void
    {
        $admin = $this->adminRepository->findById($id);
        
        if (!$admin) {
            throw new \Exception("Admin not found");
        }
        
        $this->adminRepository->delete($id);
    }
}

