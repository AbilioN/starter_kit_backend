<div>
    <div class="mb-8 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Tenants</h1>
            <p class="mt-1 text-sm text-slate-500">All provisioned tenants on the platform.</p>
        </div>
        <a href="{{ url('/god/tenants/create') }}"
           class="rounded-md bg-indigo-600 px-3.5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 transition">
            + New Tenant
        </a>
    </div>

    <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-900/5">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Name</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Subdomain</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Created via</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($tenants as $tenant)
                    <tr class="hover:bg-slate-50 transition">
                        <td class="px-4 py-3 text-sm font-medium text-slate-900">{{ $tenant->name }}</td>
                        <td class="px-4 py-3 text-sm text-slate-500 font-mono">{{ $tenant->subdomain }}</td>
                        <td class="px-4 py-3">
                            @if ($tenant->status === 'active')
                                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20">Active</span>
                            @elseif ($tenant->status === 'suspended')
                                <span class="inline-flex items-center rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-medium text-red-700 ring-1 ring-inset ring-red-600/10">Suspended</span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-700 ring-1 ring-inset ring-amber-600/20">{{ ucfirst($tenant->status) }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-500">{{ $tenant->createdVia }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ url('/god/tenants/'.$tenant->id) }}"
                               class="text-sm font-medium text-indigo-600 hover:text-indigo-500">View</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-10 text-center text-sm text-slate-500">No tenants yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
