<?php

namespace App\Livewire\InfrastructureProviders;

use App\Application\UseCases\GodAdmin\DeleteInfrastructureProviderUseCase;
use App\Domain\Repositories\InfrastructureProviderRepositoryInterface;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Index extends Component
{
    public function delete(string $providerId, DeleteInfrastructureProviderUseCase $deleteProvider): void
    {
        $deleteProvider->execute(actorId: (string) Auth::guard('godadmin')->id(), providerId: $providerId);
    }

    public function render()
    {
        $providers = app(InfrastructureProviderRepositoryInterface::class)->findAll();

        return view('livewire.infrastructure-providers.index', ['providers' => $providers])
            ->layout('layouts.god');
    }
}
