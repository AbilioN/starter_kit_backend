<?php

namespace App\Application\UseCases\Admin;

use App\Domain\Repositories\AdminRepositoryInterface;

class CreateAdminUseCase
{
    public function __construct(
        private AdminRepositoryInterface $adminRepository
    ) {}

    public function execute(string $name, string $email, string $password, bool $isActive = true): array
    {
        $admin = $this->adminRepository->create($name, $email, $password, $isActive);
        
        return $admin->toDto()->toArray();
    }
}

