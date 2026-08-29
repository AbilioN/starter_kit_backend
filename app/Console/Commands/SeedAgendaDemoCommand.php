<?php

namespace App\Console\Commands;

use App\Application\UseCases\Tenant\RunForEachTenantUseCase;
use Database\Seeders\AgendaDemoSeeder;
use Database\Seeders\AgendaSeeder;
use Illuminate\Console\Command;

/**
 * Puts a showable week of field work into a tenant.
 *
 * A command rather than a note in a README because "ready to demo" means one
 * line typed in front of someone, not a sequence recalled under pressure.
 *
 * It refuses to run outside local/staging unless forced. Demo data invents
 * clients and appointments, and the one place that must never happen by
 * accident is a production tenant during a demo — which is precisely when
 * somebody is typing quickly.
 */
class SeedAgendaDemoCommand extends Command
{
    protected $signature = 'agenda:seed-demo
        {--tenant= : Subdomain of one tenant; every tenant otherwise}
        {--force : Allow this outside local/staging}';

    protected $description = 'Seed a demo week of appointments in Porto and Lisbon (types and statuses included)';

    public function handle(RunForEachTenantUseCase $runForEachTenant): int
    {
        if (! app()->environment(['local', 'staging']) && ! $this->option('force')) {
            $this->error('Refusing to seed demo data in '.app()->environment().'. Pass --force if you meant it.');

            return self::FAILURE;
        }

        $results = $runForEachTenant->execute(function ($tenant) {
            // Vocabulary first: the demo appointments reference types and
            // statuses by slug, and a tenant provisioned before the agenda
            // existed has neither.
            (new AgendaSeeder())->setCommand($this)->run();
            (new AgendaDemoSeeder())->setCommand($this)->run();

            return 'seeded';
        }, $this->option('tenant'));

        foreach ($results as $result) {
            $this->line(sprintf(
                '  [%s] %s',
                $result['subdomain'],
                $result['status'] === 'ok' ? 'seeded' : 'FAILED — '.($result['error'] ?? 'unknown'),
            ));
        }

        $failed = count(array_filter($results, fn ($r) => $r['status'] !== 'ok'));

        $this->newLine();
        $this->info(count($results).' tenant(s) processed, '.$failed.' failed.');

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
