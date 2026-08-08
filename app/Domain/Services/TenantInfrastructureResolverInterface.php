<?php

namespace App\Domain\Services;

use App\Domain\Entities\Tenant;

/**
 * Resolves the effective infrastructure config (broadcasting/storage) for a
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
}
