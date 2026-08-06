<?php

namespace App\Application\UseCases\Landlord;

use App\Domain\Entities\Tenant;
use App\Models\Admin;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class NotifyTenantOwnerUseCase
{
    /**
     * Finds the tenant's owner Admin *in that tenant's own database* and
     * notifies them — GodAdmin/landlord-side code has no connection to any
     * specific tenant's data by default, so this mirrors what IdentifyTenant
     * does per-request, just done once here and restored afterward instead
     * of left pointed at the tenant for the rest of the request.
     *
     * Runs synchronously — see the notification classes' own docblocks for
     * why they're deliberately not ShouldQueue.
     */
    public function execute(Tenant $tenant, Notification $notification): void
    {
        $previousDatabase = config('database.connections.tenant.database');
        $previousDefault = config('database.default');
        $switched = $previousDatabase !== $tenant->databaseName;

        try {
            if ($switched) {
                config(['database.connections.tenant.database' => $tenant->databaseName]);
                DB::purge('tenant');
            }
            config(['database.default' => 'tenant']);

            Admin::where('is_tenant_owner', true)->first()?->notify($notification);
        } finally {
            if ($switched) {
                config(['database.connections.tenant.database' => $previousDatabase]);
                DB::purge('tenant');
            }
            config(['database.default' => $previousDefault]);
        }
    }
}
