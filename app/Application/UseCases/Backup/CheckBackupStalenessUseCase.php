<?php

namespace App\Application\UseCases\Backup;

use App\Domain\Entities\Backup;
use App\Domain\Repositories\BackupRepositoryInterface;
use App\Domain\Repositories\TenantRepositoryInterface;

/**
 * Which tenants have not had a successful backup within their own plan's
 * schedule.
 *
 * This is the check that separates a backup system from a directory of old
 * files. Everything else here can be working perfectly — the command scheduled,
 * the destination reachable, the ledger filling up — while a single tenant
 * quietly stops being dumped, and nothing says so until a restore is needed.
 *
 * Landlord-only by construction: it reads the ledger and the plans, and never
 * opens a tenant connection. That is what lets it answer for a tenant whose own
 * database is the broken thing, and what makes it safe to expose behind a probe.
 */
class CheckBackupStalenessUseCase
{
    public function __construct(
        private BackupRepositoryInterface $backupRepository,
        private TenantRepositoryInterface $tenantRepository,
        private ResolveBackupPolicyUseCase $resolvePolicy,
    ) {}

    /**
     * @return array{stale: array<int, array<string, mixed>>, checked: int, never: int}
     */
    public function execute(): array
    {
        $latest = $this->backupRepository->latestSuccessfulFinishedAtByTenant(Backup::KIND_DATABASE);
        $factor = (float) config('backup.staleness_factor');

        $stale = [];
        $never = 0;
        $checked = 0;

        // The landlord's own backup is checked with the tenants, not separately:
        // it is the one dump without which none of the others can be restored.
        $subjects = array_merge([null], $this->tenantRepository->findAll());

        foreach ($subjects as $tenant) {
            $policy = $this->resolvePolicy->execute($tenant);

            if (! $policy['enabled'] || $policy['frequency_hours'] === null) {
                continue;
            }

            $checked++;
            $key = $tenant?->id ?? '';
            $lastFinished = $latest[$key] ?? null;
            $deadline = now()->subHours((int) round($policy['frequency_hours'] * $factor));

            if ($lastFinished !== null && $lastFinished > $deadline) {
                continue;
            }

            if ($lastFinished === null) {
                $never++;
            }

            $stale[] = [
                'tenant' => $tenant?->subdomain ?? 'landlord',
                'tenant_id' => $tenant?->id,
                'last_successful_at' => $lastFinished?->format(DATE_ATOM),
                'frequency_hours' => $policy['frequency_hours'],
            ];
        }

        return ['stale' => $stale, 'checked' => $checked, 'never' => $never];
    }
}
