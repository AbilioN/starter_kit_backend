<?php

namespace App\Livewire\Tenants;

use App\Domain\Repositories\TenantRepositoryInterface;
use Livewire\Component;

class Index extends Component
{
    public function render()
    {
        $tenants = app(TenantRepositoryInterface::class)->findAll();

        return view('livewire.tenants.index', ['tenants' => $tenants])
            ->layout('layouts.god');
    }
}
