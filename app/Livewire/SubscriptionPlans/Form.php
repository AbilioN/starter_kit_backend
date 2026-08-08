<?php

namespace App\Livewire\SubscriptionPlans;

use App\Application\UseCases\GodAdmin\CreateSubscriptionPlanUseCase;
use App\Application\UseCases\GodAdmin\UpdateSubscriptionPlanUseCase;
use App\Domain\Repositories\InfrastructureProviderRepositoryInterface;
use App\Domain\Repositories\SubscriptionPlanRepositoryInterface;
use App\Domain\Services\IconResizingServiceInterface;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;

class Form extends Component
{
    use WithFileUploads;

    /** MB values for the storage shortcut list - 256MB/512MB/1-2-5-10-50-100GB. */
    public const STORAGE_PRESETS = [256, 512, 1024, 2048, 5120, 10240, 51200, 102400];

    public ?string $planId = null;

    public string $name = '';

    public string $slug = '';

    public ?int $priceCents = null;

    public bool $isActive = true;

    public bool $featureChat = false;

    public bool $featureFileUpload = false;

    public bool $featureNotifications = false;

    public bool $featureAiAgent = false;

    public int $maxAdmins = 5;

    public int $maxUsers = 100;

    /**
     * Shortcut list for the common case, so nobody has to do MB math for a
     * "5 GB" plan - value is either one of self::STORAGE_PRESETS' keys (as
     * a string, matching wire:model on a <select>), 'unlimited', or
     * 'custom' (reveals $maxStorageMbCustom for a manual value).
     */
    public string $maxStoragePreset = '1024';

    public ?int $maxStorageMbCustom = null;

    public bool $isPublic = false;

    public ?string $tertiaryColor = null;

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $iconUpload = null;

    public array $iconPaths = [];

    /** '' means "no default" — same convention as a clearable <select>. */
    public string $broadcastingProviderId = '';

    public string $storageProviderId = '';

    public string $aiProviderId = '';

    private SubscriptionPlanRepositoryInterface $subscriptionPlanRepository;

    // Livewire re-instantiates the component via `new static` on every
    // request (initial load and every subsequent AJAX update) without going
    // through the container, so a custom __construct() with a required
    // param blows up ("Too few arguments... 0 passed"). boot() is Livewire's
    // own hook for this — it IS called through the container with method
    // injection, automatically, before mount() and before every update.
    public function boot(SubscriptionPlanRepositoryInterface $subscriptionPlanRepository): void
    {
        $this->subscriptionPlanRepository = $subscriptionPlanRepository;
    }

    public function mount(?string $planId = null): void
    {
        if (! $planId) {
            return;
        }

        $plan = $this->subscriptionPlanRepository->findById($planId);

        if (! $plan) {
            abort(404);
        }

        $this->planId = $plan->id;
        $this->name = $plan->name;
        $this->slug = $plan->slug;
        $this->priceCents = $plan->priceCents;
        $this->isActive = $plan->isActive;
        $this->featureChat = (bool) ($plan->features['chat'] ?? false);
        $this->featureFileUpload = (bool) ($plan->features['file_upload'] ?? false);
        $this->featureNotifications = (bool) ($plan->features['notifications'] ?? false);
        $this->featureAiAgent = (bool) ($plan->features['ai_agent'] ?? false);
        $this->maxAdmins = (int) ($plan->limits['max_admins'] ?? 5);
        $this->maxUsers = (int) ($plan->limits['max_users'] ?? 100);

        // Key present but null means "unlimited" (see UploadFileUseCase /
        // ChangeTenantSubscriptionPlanUseCase - absence of a storage limit
        // setting is how "no cap" is represented everywhere it's enforced).
        // Key absent entirely (older plan, field didn't exist yet) defaults
        // to the same 1GB the field always defaulted to.
        if (array_key_exists('max_storage_mb', $plan->limits) && $plan->limits['max_storage_mb'] === null) {
            $this->maxStoragePreset = 'unlimited';
        } else {
            $storageMb = (int) ($plan->limits['max_storage_mb'] ?? 1024);
            if (in_array($storageMb, self::STORAGE_PRESETS, true)) {
                $this->maxStoragePreset = (string) $storageMb;
            } else {
                $this->maxStoragePreset = 'custom';
                $this->maxStorageMbCustom = $storageMb;
            }
        }

        $this->isPublic = $plan->isPublic;
        $this->tertiaryColor = $plan->tertiaryColor;
        $this->iconPaths = $plan->iconPaths;
        $this->broadcastingProviderId = $plan->broadcastingProviderId ?? '';
        $this->storageProviderId = $plan->storageProviderId ?? '';
        $this->aiProviderId = $plan->aiProviderId ?? '';
    }

    public function save(
        CreateSubscriptionPlanUseCase $createSubscriptionPlan,
        UpdateSubscriptionPlanUseCase $updateSubscriptionPlan,
        IconResizingServiceInterface $iconResizingService,
    ): void {
        $this->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|regex:/^[a-z0-9-]+$/',
            'priceCents' => 'nullable|integer|min:0',
            'tertiaryColor' => 'nullable|string|regex:/^#[0-9a-fA-F]{6}$/',
            'iconUpload' => 'nullable|image|max:4096',
            'maxStorageMbCustom' => 'required_if:maxStoragePreset,custom|nullable|integer|min:1',
        ]);

        $actorId = Auth::guard('godadmin')->id();
        $features = [
            'chat' => $this->featureChat,
            'file_upload' => $this->featureFileUpload,
            'notifications' => $this->featureNotifications,
            'ai_agent' => $this->featureAiAgent,
        ];
        $limits = [
            'max_admins' => $this->maxAdmins,
            'max_users' => $this->maxUsers,
            // null = unlimited - see UploadFileUseCase::enforceStorageLimit()
            // and ChangeTenantSubscriptionPlanUseCase::syncLimitsFromPlan(),
            // both treat "no limits.max_storage_mb setting" as no cap.
            'max_storage_mb' => match ($this->maxStoragePreset) {
                'unlimited' => null,
                'custom' => $this->maxStorageMbCustom,
                default => (int) $this->maxStoragePreset,
            },
        ];

        $iconPaths = $this->iconUpload
            ? $iconResizingService->generateSizes($this->iconUpload, 'subscription-plan-icons')
            : null;

        if ($this->planId) {
            $updateSubscriptionPlan->execute(
                actorId: $actorId,
                planId: $this->planId,
                name: $this->name,
                priceCents: $this->priceCents,
                features: $features,
                limits: $limits,
                isActive: $this->isActive,
                isPublic: $this->isPublic,
                tertiaryColor: $this->tertiaryColor,
                iconPaths: $iconPaths,
                broadcastingProviderId: $this->broadcastingProviderId ?: null,
                storageProviderId: $this->storageProviderId ?: null,
                aiProviderId: $this->aiProviderId ?: null,
                clearBroadcastingProvider: $this->broadcastingProviderId === '',
                clearStorageProvider: $this->storageProviderId === '',
                clearAiProvider: $this->aiProviderId === '',
            );
        } else {
            $createSubscriptionPlan->execute(
                actorId: $actorId,
                name: $this->name,
                slug: $this->slug,
                priceCents: $this->priceCents,
                features: $features,
                limits: $limits,
                isActive: $this->isActive,
                isPublic: $this->isPublic,
                tertiaryColor: $this->tertiaryColor,
                iconPaths: $iconPaths,
                broadcastingProviderId: $this->broadcastingProviderId ?: null,
                storageProviderId: $this->storageProviderId ?: null,
                aiProviderId: $this->aiProviderId ?: null,
            );
        }

        $this->redirect('/god/subscription-plans', navigate: false);
    }

    public function render()
    {
        $providerRepository = app(InfrastructureProviderRepositoryInterface::class);

        return view('livewire.subscription-plans.form', [
            'broadcastingProviders' => $providerRepository->findByType('broadcasting'),
            'storageProviders' => $providerRepository->findByType('storage'),
            'aiProviders' => $providerRepository->findByType('ai'),
        ])->layout('layouts.god');
    }
}
