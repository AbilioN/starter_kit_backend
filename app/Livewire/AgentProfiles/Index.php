<?php

namespace App\Livewire\AgentProfiles;

use App\Application\UseCases\GodAdmin\DeleteAgentProfileUseCase;
use App\Domain\Repositories\AgentProfileRepositoryInterface;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Index extends Component
{
    public function delete(string $profileId, DeleteAgentProfileUseCase $deleteProfile): void
    {
        $deleteProfile->execute(actorId: (string) Auth::guard('godadmin')->id(), profileId: $profileId);
    }

    public function render()
    {
        $profiles = app(AgentProfileRepositoryInterface::class)->findAll();

        return view('livewire.agent-profiles.index', ['profiles' => $profiles])
            ->layout('layouts.god');
    }
}
