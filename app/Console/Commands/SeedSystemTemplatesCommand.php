<?php

namespace App\Console\Commands;

use App\Application\UseCases\Tenant\SeedSystemTemplatesForAllTenantsUseCase;
use DomainException;
use Illuminate\Console\Command;

class SeedSystemTemplatesCommand extends Command
{
    protected $signature = 'tenant:seed-system-templates
        {--tenant= : Only seed this tenant\'s subdomain (default: every tenant)}';

    protected $description = 'Seed/refresh the system email template slots (welcome, password reset, password changed) on every tenant database, or one via --tenant';

    public function handle(SeedSystemTemplatesForAllTenantsUseCase $seedSystemTemplates): int
    {
        try {
            $results = $seedSystemTemplates->execute(subdomain: $this->option('tenant'));
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $failed = 0;

        foreach ($results as $result) {
            if ($result['status'] === 'ok') {
                $this->info("[{$result['subdomain']}] seeded");
            } else {
                $failed++;
                $this->error("[{$result['subdomain']}] FAILED: {$result['error']}");
            }
        }

        $this->newLine();
        $this->info(sprintf('%d tenant(s) seeded, %d failed.', count($results) - $failed, $failed));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
