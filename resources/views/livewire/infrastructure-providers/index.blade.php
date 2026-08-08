<div>
    <div class="mb-8 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Infrastructure Providers</h1>
            <p class="mt-1 text-sm text-slate-500">Broadcasting (Pusher) apps and storage (S3) backends — assign one as a plan's default or a tenant's override.</p>
        </div>
        <a href="{{ url('/god/infrastructure-providers/create') }}"
           class="rounded-md bg-indigo-600 px-3.5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 transition">
            + New Provider
        </a>
    </div>

    <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-900/5">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Name</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Type</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($providers as $provider)
                    <tr class="hover:bg-slate-50 transition">
                        <td class="px-4 py-3 text-sm font-medium text-slate-900">{{ $provider->name }}</td>
                        <td class="px-4 py-3">
                            @if ($provider->type === 'broadcasting')
                                <span class="inline-flex items-center rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-medium text-indigo-700 ring-1 ring-inset ring-indigo-600/20">Broadcasting</span>
                            @elseif ($provider->type === 'storage')
                                <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-700 ring-1 ring-inset ring-amber-600/20">Storage</span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-violet-50 px-2.5 py-0.5 text-xs font-medium text-violet-700 ring-1 ring-inset ring-violet-600/20">AI</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if ($provider->isActive)
                                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20">Active</span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600 ring-1 ring-inset ring-slate-500/10">Inactive</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ url('/god/infrastructure-providers/'.$provider->id.'/edit') }}"
                               class="text-sm font-medium text-indigo-600 hover:text-indigo-500">Edit</a>
                            <button wire:click="delete('{{ $provider->id }}')" wire:confirm="Delete this provider? Any plan/tenant pointing at it falls back to the global default."
                                    class="ml-4 text-sm font-medium text-red-600 hover:text-red-500">Delete</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-10 text-center text-sm text-slate-500">No infrastructure providers yet — plans and tenants use the global default config.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
