<?php

namespace App\Application\UseCases\Tenant;

class GetTenantThemeUseCase
{
    public function execute(): array
    {
        $tenant = app('currentTenant');

        return [
            'name' => $tenant->name,
            'primary_color' => $tenant->theme_primary_color,
            'secondary_color' => $tenant->theme_secondary_color,
            'logo_url' => $tenant->logo_path ? asset('storage/'.$tenant->logo_path) : null,
        ];
    }
}
