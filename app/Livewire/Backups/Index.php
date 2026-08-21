<?php

namespace App\Livewire\Backups;

use App\Application\UseCases\Backup\CheckBackupStalenessUseCase;
use App\Application\UseCases\Backup\ResolveBackupPolicyUseCase;
use App\Domain\Entities\Backup;
use App\Domain\Repositories\BackupRepositoryInterface;
use App\Domain\Repositories\TenantRepositoryInterface;
use App\Jobs\RunBackupJob;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

/**
 * The operator's view of the backup ledger.
 *
 * Deliberately GodAdmin-only, and deliberately without a restore button: a
 * restore replaces a customer's live data, and it stays an explicit CLI
 * procedure with an operator on the other end (`backup:restore`). The page
 * hands over the exact command instead of pretending a click is enough.
 */
class Index extends Component
{
    /** Empty string = the landlord's own backups. */
    public string $selectedTenantId = '';

    public string $message = '';

    public function runNow(string $kind): void
    {
        $tenantId = $this->selectedTenantId ?: null;

        if ($kind === Backup::KIND_FILES && $tenantId === null) {
            $this->message = 'The landlord has no uploaded files — only its database is backed up.';

            return;
        }

        // Queued, never inline: a real dump takes minutes and would time out
        // the request while holding a php-fpm worker.
        RunBackupJob::dispatch($tenantId, $kind);

        $this->message = 'Backup queued. It will appear below once the worker finishes it.';
    }

    public function render()
    {
        $backupRepository = app(BackupRepositoryInterface::class);
        $tenantRepository = app(TenantRepositoryInterface::class);
        $resolvePolicy = app(ResolveBackupPolicyUseCase::class);

        $tenants = $tenantRepository->findAll();
        $tenant = $this->selectedTenantId
            ? $tenantRepository->findById($this->selectedTenantId)
            : null;

        // Shares the memoised result with the readiness probe rather than
        // recomputing it per page view.
        $staleness = Cache::remember(
            'health:backup-staleness',
            now()->addMinutes(5),
            fn () => app(CheckBackupStalenessUseCase::class)->execute(),
        );

        return view('livewire.backups.index', [
            'tenants' => $tenants,
            'tenant' => $tenant,
            'policy' => $resolvePolicy->execute($tenant),
            'backups' => $backupRepository->findForTenant($this->selectedTenantId ?: null, null, 25),
            'storedBytes' => $backupRepository->totalStoredBytes($this->selectedTenantId ?: null),
            'staleSubjects' => array_column($staleness['stale'], 'tenant'),
        ])->layout('layouts.god');
    }
}
