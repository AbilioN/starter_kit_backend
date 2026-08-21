<?php

namespace App\Console\Commands;

use App\Domain\Repositories\BackupRepositoryInterface;
use App\Domain\Repositories\TenantRepositoryInterface;
use Illuminate\Console\Command;

/**
 * The restore catalog, from the CLI. `backup:restore` needs an id, and this is
 * where the id comes from when the panel is not reachable — which is a state
 * this command has to keep working in.
 */
class ListBackupsCommand extends Command
{
    protected $signature = 'backup:list
        {--tenant= : Subdomain, or omit for the landlord\'s own backups}
        {--kind= : database|files}
        {--limit=20}';

    protected $description = 'List recorded backups for a tenant (or the landlord)';

    public function handle(
        BackupRepositoryInterface $backupRepository,
        TenantRepositoryInterface $tenantRepository,
    ): int {
        $subdomain = $this->option('tenant');
        $tenantId = null;

        if ($subdomain) {
            $tenant = $tenantRepository->findBySubdomain($subdomain);

            if ($tenant === null) {
                $this->error("No tenant found with subdomain '{$subdomain}'.");

                return self::FAILURE;
            }

            $tenantId = $tenant->id;
        }

        $backups = $backupRepository->findForTenant($tenantId, $this->option('kind'), (int) $this->option('limit'));

        if ($backups === []) {
            $this->warn('No backups recorded for '.($subdomain ?? 'the landlord').'.');

            return self::SUCCESS;
        }

        $this->table(
            ['id', 'kind', 'status', 'started', 'size (MB)', 'enc', 'destination'],
            array_map(fn ($backup) => [
                $backup->id,
                $backup->kind,
                $backup->status,
                $backup->startedAt?->format('Y-m-d H:i'),
                $backup->sizeBytes === null ? '-' : number_format($backup->sizeBytes / 1024 / 1024, 1),
                $backup->isEncrypted ? 'yes' : 'no',
                $backup->destinationPath ?? ($backup->error ? 'ERROR: '.mb_substr($backup->error, 0, 60) : '-'),
            ], $backups),
        );

        return self::SUCCESS;
    }
}
