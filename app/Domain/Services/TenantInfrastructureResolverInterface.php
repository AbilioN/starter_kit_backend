<?php

namespace App\Domain\Services;

use App\Domain\Entities\Tenant;

/**
 * Resolves the effective infrastructure config (broadcasting/storage/ai/backup) for a
 * tenant: the tenant's own override if set, else its plan's default, else
 * null. Null means "nothing configured" — the caller must leave config()
 * untouched in that case, so a tenant/plan with no InfrastructureProvider
 * assigned keeps using whatever the global .env-driven config already is,
 * exactly like today. Purely additive/opt-in, never a required migration
 * of existing behaviour.
 *
 * Takes the domain Tenant entity (not the Eloquent model) so it works
 * identically from both places tenant context is established — the HTTP
 * path (IdentifyTenant, which has the Eloquent model but converts via
 * ->toEntity()) and the queued-job path (EstablishTenantConnection, which
 * re-fetches the tenant from the landlord connection and has no
 * app('currentTenant') binding to lean on either way).
 */
interface TenantInfrastructureResolverInterface
{
    /**
     * @return array|null Ready to assign directly to
     *   config(['broadcasting.connections.pusher' => ...]).
     */
    public function resolveBroadcastingConfig(Tenant $tenant): ?array;

    /**
     * @return array|null Ready to assign directly to
     *   config(['filesystems.disks.s3' => ...]).
     */
    public function resolveStorageConfig(Tenant $tenant): ?array;

    /**
     * Unlike broadcasting/storage, this isn't swapped into Laravel's own
     * config() — nothing in this app calls OpenAI directly. Callers (i.e.
     * ProcessOpenAIRequest) embed the result directly into the Redis
     * payload sent to the Python worker.
     *
     * @return array{api_key: ?string, model: ?string, system_prompt: ?string}|null
     */
    public function resolveAiConfig(Tenant $tenant): ?array;

    /**
     * Where this tenant's backups are written.
     *
     * The one type whose null case is dangerous. For the three above, "nothing
     * resolved" means "keep using the global .env" and the product carries on
     * working; here the same shrug means a tenant quietly stops being backed
     * up, and nobody finds out until a restore is needed. So this method never
     * returns null: it falls back to the global BACKUP_* disk, and if that is
     * not configured either it throws — a recorded, visible failure instead of
     * a silent skip.
     *
     * It is also the only one resolved without a request. Backups run in the
     * scheduler: no subdomain, no IdentifyTenant, no middleware. The result is
     * meant for Storage::build(), NOT for config(['filesystems.disks.s3' => ...])
     * — overwriting the global disk inside a CLI process that then loops over
     * every tenant is how one tenant's dump lands in another tenant's bucket.
     *
     * @param  Tenant|null  $tenant  null resolves the landlord's own destination
     * @return array{provider_id: ?string, disk_name: string, config: array}
     *
     * @throws \App\Domain\Exceptions\BackupDestinationException
     */
    public function resolveBackupConfig(?Tenant $tenant): array;

    /**
     * The destination a specific *existing* backup was written to, by provider
     * id, for pruning and restoring.
     *
     * Not the same question as resolveBackupConfig(): a tenant may have been
     * moved to a different provider since a backup was taken, and that copy is
     * still sitting where it was actually written. Resolving the tenant's
     * current destination to delete or restore an old file would reach into the
     * wrong bucket — or, worse, silently find nothing there and report success.
     *
     * @return array{provider_id: ?string, disk_name: string, config: array}
     *
     * @throws \App\Domain\Exceptions\BackupDestinationException
     */
    public function resolveBackupConfigById(?string $providerId): array;
}
