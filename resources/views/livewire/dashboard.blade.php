<div>
    <h1>Dashboard</h1>

    <div style="display: flex; gap: 1rem;">
        <div class="card">
            <div>Tenants</div>
            <strong>{{ $tenantCount }}</strong> ({{ $activeTenantCount }} active)
        </div>
        <div class="card">
            <div>Subscription Plans</div>
            <strong>{{ $planCount }}</strong>
        </div>
    </div>
</div>
