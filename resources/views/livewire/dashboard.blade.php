<div>
    <div class="mb-8">
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Dashboard</h1>
        <p class="mt-1 text-sm text-slate-500">Platform-wide overview.</p>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
            <div class="text-sm font-medium text-slate-500">Tenants</div>
            <div class="mt-2 text-3xl font-semibold tracking-tight text-slate-900">{{ $tenantCount }}</div>
            <div class="mt-1 text-sm text-slate-500">{{ $activeTenantCount }} active</div>
        </div>

        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
            <div class="text-sm font-medium text-slate-500">Subscription Plans</div>
            <div class="mt-2 text-3xl font-semibold tracking-tight text-slate-900">{{ $planCount }}</div>
            <div class="mt-1 text-sm text-slate-500">available</div>
        </div>

        <a href="{{ url('/god/tenants/create') }}"
           class="group flex flex-col justify-center rounded-xl border-2 border-dashed border-slate-300 p-6 text-center hover:border-indigo-400 hover:bg-indigo-50/50 transition">
            <span class="text-sm font-medium text-slate-600 group-hover:text-indigo-600">+ Provision a new tenant</span>
        </a>
    </div>
</div>
