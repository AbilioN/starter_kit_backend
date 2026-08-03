<div class="max-w-2xl">
    <div class="mb-8">
        <a href="{{ url('/god/tenants') }}" class="text-sm font-medium text-slate-500 hover:text-slate-700">&larr; Tenants</a>
        <div class="mt-2 flex items-center gap-3">
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900">{{ $tenant->name }}</h1>
            @if ($tenant->status === 'active')
                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20">Active</span>
            @elseif ($tenant->status === 'suspended')
                <span class="inline-flex items-center rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-medium text-red-700 ring-1 ring-inset ring-red-600/10">Suspended</span>
            @else
                <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-700 ring-1 ring-inset ring-amber-600/20">{{ ucfirst($tenant->status) }}</span>
            @endif
        </div>
    </div>

    <div class="rounded-xl bg-white shadow-sm ring-1 ring-slate-900/5">
        <dl class="divide-y divide-slate-100">
            <div class="grid grid-cols-3 gap-4 px-6 py-4">
                <dt class="text-sm font-medium text-slate-500">Subdomain</dt>
                <dd class="col-span-2 text-sm text-slate-900 font-mono">{{ $tenant->subdomain }}</dd>
            </div>
            <div class="grid grid-cols-3 gap-4 px-6 py-4">
                <dt class="text-sm font-medium text-slate-500">Database</dt>
                <dd class="col-span-2 text-sm text-slate-900 font-mono">{{ $tenant->databaseName }}</dd>
            </div>
            <div class="grid grid-cols-3 gap-4 px-6 py-4">
                <dt class="text-sm font-medium text-slate-500">Created via</dt>
                <dd class="col-span-2 text-sm text-slate-900">{{ $tenant->createdVia }}</dd>
            </div>
        </dl>
    </div>

    <div class="mt-6 flex justify-end">
        <button wire:click="toggleStatus" wire:confirm="Are you sure?"
                class="rounded-md px-3.5 py-2.5 text-sm font-semibold shadow-sm transition
                       {{ $tenant->isActive() ? 'bg-red-600 text-white hover:bg-red-500' : 'bg-emerald-600 text-white hover:bg-emerald-500' }}">
            {{ $tenant->isActive() ? 'Suspend Tenant' : 'Reactivate Tenant' }}
        </button>
    </div>
</div>
