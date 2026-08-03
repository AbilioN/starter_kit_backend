<div>
    <h1>{{ $tenant->name }}</h1>

    <div class="card">
        <p><strong>Subdomain:</strong> {{ $tenant->subdomain }}</p>
        <p><strong>Database:</strong> {{ $tenant->databaseName }}</p>
        <p><strong>Status:</strong> {{ $tenant->status }}</p>
        <p><strong>Created via:</strong> {{ $tenant->createdVia }}</p>
    </div>

    <button wire:click="toggleStatus" wire:confirm="Are you sure?">
        {{ $tenant->isActive() ? 'Suspend' : 'Reactivate' }} Tenant
    </button>

    <p><a href="{{ url('/god/tenants') }}">&larr; Back to Tenants</a></p>
</div>
