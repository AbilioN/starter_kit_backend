<?php

namespace App\Livewire\AgentProfiles;

use App\Application\UseCases\GodAdmin\CreateAgentProfileUseCase;
use App\Application\UseCases\GodAdmin\UpdateAgentProfileUseCase;
use App\Domain\Repositories\AgentProfileRepositoryInterface;
use App\Domain\Repositories\SubscriptionPlanRepositoryInterface;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;

class Form extends Component
{
    use WithFileUploads;

    /** Common OpenAI chat models — shortcut list so nobody has to remember
     * exact model strings; 'custom' reveals $modelCustom for anything else
     * (a future model, an Azure/OpenRouter-style alias, etc). */
    public const MODEL_PRESETS = ['gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo', 'gpt-4', 'gpt-3.5-turbo'];

    public ?string $profileId = null;

    public string $name = '';

    public string $description = '';

    public string $systemPrompt = '';

    /** '' = inherit (tenant BYOK/global default), one of MODEL_PRESETS, or 'custom'. */
    public string $modelPreset = '';

    public string $modelCustom = '';

    public bool $isActive = true;

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $avatarUpload = null;

    public ?string $currentAvatar = null;

    /** @var array<int, string> */
    public array $selectedPlanIds = [];

    private AgentProfileRepositoryInterface $agentProfileRepository;

    // See SubscriptionPlans/Form.php's boot() for why this isn't __construct().
    public function boot(AgentProfileRepositoryInterface $agentProfileRepository): void
    {
        $this->agentProfileRepository = $agentProfileRepository;
    }

    public function mount(?string $profileId = null): void
    {
        if (! $profileId) {
            return;
        }

        $profile = $this->agentProfileRepository->findById($profileId);

        if (! $profile) {
            abort(404);
        }

        $this->profileId = $profile->id;
        $this->name = $profile->name;
        $this->description = $profile->description ?? '';
        $this->systemPrompt = $profile->systemPrompt ?? '';

        if (! $profile->model) {
            $this->modelPreset = '';
        } elseif (in_array($profile->model, self::MODEL_PRESETS, true)) {
            $this->modelPreset = $profile->model;
        } else {
            $this->modelPreset = 'custom';
            $this->modelCustom = $profile->model;
        }

        $this->isActive = $profile->isActive;
        $this->currentAvatar = $profile->avatar;
        $this->selectedPlanIds = $this->agentProfileRepository->getPlanIds($profileId);
    }

    public function save(
        CreateAgentProfileUseCase $createProfile,
        UpdateAgentProfileUseCase $updateProfile,
    ): void {
        $this->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'systemPrompt' => 'nullable|string',
            'modelCustom' => 'required_if:modelPreset,custom|nullable|string|max:255',
            'avatarUpload' => 'nullable|image|max:2048',
        ]);

        $model = match ($this->modelPreset) {
            '' => null,
            'custom' => $this->modelCustom ?: null,
            default => $this->modelPreset,
        };

        $avatarPath = $this->avatarUpload?->store('agent-profile-avatars', 'public');
        $actorId = (string) Auth::guard('godadmin')->id();

        if ($this->profileId) {
            $updateProfile->execute(
                actorId: $actorId,
                profileId: $this->profileId,
                name: $this->name,
                description: $this->description ?: null,
                avatar: $avatarPath,
                systemPrompt: $this->systemPrompt ?: null,
                model: $model,
                isActive: $this->isActive,
                clearDescription: $this->description === '',
                clearSystemPrompt: $this->systemPrompt === '',
                clearModel: $model === null,
                planIds: $this->selectedPlanIds,
            );
        } else {
            $createProfile->execute(
                actorId: $actorId,
                name: $this->name,
                description: $this->description ?: null,
                avatar: $avatarPath,
                systemPrompt: $this->systemPrompt ?: null,
                model: $model,
                isActive: $this->isActive,
                planIds: $this->selectedPlanIds,
            );
        }

        $this->redirect('/god/agent-profiles', navigate: false);
    }

    public function render()
    {
        $plans = app(SubscriptionPlanRepositoryInterface::class)->findActive();

        return view('livewire.agent-profiles.form', ['plans' => $plans])
            ->layout('layouts.god');
    }
}
