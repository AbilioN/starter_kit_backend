<div>
    <div class="mb-8 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Subscription Plans</h1>
            <p class="mt-1 text-sm text-slate-500">Manage the plans tenants can subscribe to.</p>
        </div>
        <a href="{{ url('/god/subscription-plans/create') }}"
           class="rounded-md bg-indigo-600 px-3.5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 transition">
            + New Plan
        </a>
    </div>

    <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-900/5">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Name</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Slug</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Price</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Visibility</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($plans as $plan)
                    <tr class="hover:bg-slate-50 transition">
                        <td class="px-4 py-3 text-sm font-medium text-slate-900">{{ $plan->name }}</td>
                        <td class="px-4 py-3 text-sm text-slate-500 font-mono">{{ $plan->slug }}</td>
                        <td class="px-4 py-3 text-sm text-slate-700">
                            {{ $plan->priceCents !== null ? '$'.number_format($plan->priceCents / 100, 2) : '—' }}
                        </td>
                        <td class="px-4 py-3">
                            @if ($plan->isActive)
                                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20">Active</span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600 ring-1 ring-inset ring-slate-500/10">Inactive</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if ($plan->isPublic)
                                <span class="inline-flex items-center rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-medium text-indigo-700 ring-1 ring-inset ring-indigo-600/20">Public</span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600 ring-1 ring-inset ring-slate-500/10">Private</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ url('/god/subscription-plans/'.$plan->id.'/edit') }}"
                               class="text-sm font-medium text-indigo-600 hover:text-indigo-500">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-10 text-center text-sm text-slate-500">No subscription plans yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
