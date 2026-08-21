<?php

namespace App\Console\Commands;

use App\Application\UseCases\Tenant\RunForEachTenantUseCase;
use DomainException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Deletes expired Sanctum tokens from every tenant database.
 *
 * Laravel ships `sanctum:prune-expired`, but it runs against the default
 * connection only — under database-per-tenant that reaches exactly zero of the
 * tables that actually hold tokens.
 *
 * This became necessary the moment GodAdmin support sessions shipped (5.6):
 * they mint tokens with a 30-minute expiry, and an expired token is dead for
 * authentication but stays in the table forever. Rows that only accumulate are
 * a slow leak; rows that record who accessed what are also a liability worth
 * clearing on a schedule rather than never.
 */
class PruneExpiredTokensCommand extends Command
{
    protected $signature = 'tenant:prune-tokens
        {--tenant= : Only prune this tenant\'s subdomain (default: every tenant)}
        {--hours=24 : Keep tokens that expired less recently than this many hours ago}';

    protected $description = 'Delete expired Sanctum tokens from every tenant database';

    public function handle(RunForEachTenantUseCase $runForEachTenant): int
    {
        $hours = (int) $this->option('hours');

        // A grace period rather than deleting the instant a token expires: a
        // token that just lapsed is still useful evidence while someone is
        // looking at "why was I logged out", and the row costs nothing for a day.
        $cutoff = now()->subHours($hours);

        try {
            $results = $runForEachTenant->execute(
                callback: fn () => DB::connection('tenant')
                    ->table('personal_access_tokens')
                    ->whereNotNull('expires_at')
                    ->where('expires_at', '<', $cutoff)
                    ->delete(),
                subdomain: $this->option('tenant'),
            );
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $failed = 0;
        $deleted = 0;

        foreach ($results as $result) {
            if ($result['status'] === 'ok') {
                $deleted += (int) $result['result'];
                $this->line("[{$result['subdomain']}] pruned {$result['result']} token(s)");
            } else {
                $failed++;
                $this->error("[{$result['subdomain']}] FAILED: {$result['error']}");
            }
        }

        $this->newLine();
        $this->info(sprintf('%d token(s) pruned across %d tenant(s), %d failed.', $deleted, count($results) - $failed, $failed));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
