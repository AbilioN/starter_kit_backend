<?php
namespace App\Application\UseCases\Auth;

use App\Domain\Repositories\AdminRepositoryInterface;
use App\Domain\Exceptions\RegistrationException;

class AdminRegisterUseCase
{
    public function __construct(
        private AdminRepositoryInterface $adminRepository
    ) {}

    public function execute(string $name, string $email, string $password): array
    {
        $existingAdmin = $this->adminRepository->findByEmail($email);
        if ($existingAdmin) {
            throw new RegistrationException('Admin with this email already exists');
        }

        $admin = $this->adminRepository->create($name, $email, $password);
        return [
            'admin' => [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
                'is_active' => $admin->isActive,
                'created_at' => now()->format('Y-m-d H:i:s')
            ]
        ];
    }
} 