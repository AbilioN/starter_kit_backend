<div>
    <div class="mb-8 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Financial Report</h1>
            <p class="mt-1 text-sm text-slate-500">Mock revenue — no real payment gateway is connected yet.</p>
        </div>
        <a href="{{ url('/god/financial-report/export') }}"
           class="rounded-md bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 shadow-sm ring-1 ring-inset ring-slate-300 hover:bg-slate-50 transition">
            Export CSV
        </a>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
            <div class="text-sm font-medium text-slate-500">Current MRR (mock)</div>
            <div class="mt-2 text-3xl font-semibold tracking-tight text-slate-900">${{ number_format($report['current_mrr_cents'] / 100, 2) }}</div>
        </div>
        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
            <div class="text-sm font-medium text-slate-500">Total Lifetime Revenue (mock)</div>
            <div class="mt-2 text-3xl font-semibold tracking-tight text-slate-900">${{ number_format($report['total_lifetime_revenue_cents'] / 100, 2) }}</div>
        </div>
        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
            <div class="text-sm font-medium text-slate-500">Active Paying Tenants</div>
            <div class="mt-2 text-3xl font-semibold tracking-tight text-slate-900">{{ $report['active_paying_tenant_count'] }}</div>
        </div>
    </div>

    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
            <div class="text-sm font-medium text-slate-500">Public plans</div>
            <div class="mt-2 text-2xl font-semibold tracking-tight text-slate-900">${{ number_format($report['visibility_split']['public']['total_price_cents'] / 100, 2) }}</div>
            <div class="mt-1 text-sm text-slate-500">{{ $report['visibility_split']['public']['tenant_count'] }} tenants</div>
        </div>
        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
            <div class="text-sm font-medium text-slate-500">Private plans</div>
            <div class="mt-2 text-2xl font-semibold tracking-tight text-slate-900">${{ number_format($report['visibility_split']['private']['total_price_cents'] / 100, 2) }}</div>
            <div class="mt-1 text-sm text-slate-500">{{ $report['visibility_split']['private']['tenant_count'] }} tenants</div>
        </div>
    </div>

    <div class="mt-6 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-900/5">
        <div class="px-6 py-4 border-b border-slate-100">
            <h2 class="text-sm font-semibold text-slate-900">Revenue by Plan</h2>
        </div>
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Plan</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Visibility</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Tenants</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Revenue</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($report['by_plan'] as $row)
                    <tr class="hover:bg-slate-50 transition">
                        <td class="px-4 py-3 text-sm font-medium text-slate-900">{{ $row['plan_name'] }}</td>
                        <td class="px-4 py-3">
                            @if ($row['is_public'])
                                <span class="inline-flex items-center rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-medium text-indigo-700 ring-1 ring-inset ring-indigo-600/20">Public</span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600 ring-1 ring-inset ring-slate-500/10">Private</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-700">{{ $row['tenant_count'] }}</td>
                        <td class="px-4 py-3 text-sm text-slate-700">${{ number_format($row['total_price_cents'] / 100, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-10 text-center text-sm text-slate-500">No active paying tenants yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-900/5">
        <div class="px-6 py-4 border-b border-slate-100">
            <h2 class="text-sm font-semibold text-slate-900">Monthly Revenue (last 12 months)</h2>
        </div>
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Month</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Revenue</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($report['monthly_revenue'] as $row)
                    <tr class="hover:bg-slate-50 transition">
                        <td class="px-4 py-3 text-sm text-slate-900">{{ $row['month'] }}</td>
                        <td class="px-4 py-3 text-sm text-slate-700">${{ number_format($row['total_cents'] / 100, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
