<?php

namespace App\Application\UseCases\Admin;

use App\Application\Services\AdminProfilePresenter;
use App\Models\Admin;

class GetAdminProfileUseCase
{
    public function execute(string $adminId): array
    {
        return AdminProfilePresenter::response(Admin::findOrFail($adminId));
    }
}
