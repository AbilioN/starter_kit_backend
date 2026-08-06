<?php

namespace App\Application\UseCases\Admin;

use App\Application\UseCases\Tenant\EnforcePlanLimitUseCase;
use App\Domain\Repositories\AdminRepositoryInterface;
use App\Models\Admin;

class CreateAdminUseCase
{
    public function __construct(
        private AdminRepositoryInterface $adminRepository,
        private EnforcePlanLimitUseCase $enforcePlanLimit,
    ) {}

    public function execute(string $name, string $email, string $password, bool $isActive = true): array
    {
        $this->enforcePlanLimit->execute('max_admins', fn () => Admin::count());

        $admin = $this->adminRepository->create($name, $email, $password, $isActive);

        return $admin->toDto()->toArray();
    }
}

