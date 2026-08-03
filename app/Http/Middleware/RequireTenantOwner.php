<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Admin;

class RequireTenantOwner
{
    /**
     * Gates tenant-owner-only actions (subscription plan, branding) by the
     * admins.is_tenant_owner boolean, not the permission system - per
     * docs/03-multitenancy-plan.md it's a single flag, not a resource/action.
     * Runs after admin.auth, so $request->user() is already a known-active Admin.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof Admin || ! $user->is_tenant_owner) {
            return response()->json(['message' => 'Access denied. Tenant owner privileges required.'], 403);
        }

        return $next($request);
    }
}
