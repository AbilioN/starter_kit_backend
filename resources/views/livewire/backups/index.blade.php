<div>
    <div class="mb-8">
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Backups</h1>
        <p class="mt-1 text-sm text-slate-500">
            Every attempt is recorded — including the ones that failed. A backup system nobody checks is a directory of old files.
        </p>
    </div>

    @if ($message)
        <div class="mb-6 rounded-lg bg-indigo-50 px-4 py-3 text-sm text-indigo-800 ring-1 ring-inset ring-indigo-600/20">
            {{ $message }}
        </div>
    @endif

    @if (count($staleSubjects) > 0)
        <div class="mb-6 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-900 ring-1 ring-inset ring-amber-600/20">
            <strong class="font-semibold">Overdue:</strong>
            {{ implode(', ', $staleSubjects) }}
            — no successful backup within the plan's own schedule.
        </div>
    @endif

    <div class="mb-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-4">
            <div class="sm:col-span-2">
                <label for="selectedTenantId" class="block text-sm font-medium text-slate-700">Subject</label>
                <select id="selectedTenantId" wire:model.live="selectedTenantId"
                        class="mt-1.5 block w-full rounded-md border-0 px-3 py-2 text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 text-sm">
                    <option value="">Landlord (tenants, plans, providers)</option>
                    @foreach ($tenants as $option)
                        <option value="{{ $option->id }}">{{ $option->subdomain }} — {{ $option->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <span class="block text-xs font-medium text-slate-500">Policy</span>
                <p class="mt-1.5 text-sm text-slate-900">
                    @if (! $policy['enabled'] || $policy['frequency_hours'] === null)
                        <span class="font-semibold text-amber-700">Backups off for this plan</span>
                    @else
                        every {{ $policy['frequency_hours'] }}h · keep {{ $policy['retention_days'] }}d ·
                        {{ $policy['max_total_mb'] === null ? 'uncapped' : $policy['max_total_mb'] . ' MB' }}
                    @endif
                </p>
            </div>
            <div>
                <span class="block text-xs font-medium text-slate-500">Stored</span>
                <p class="mt-1.5 text-sm text-slate-900">{{ number_format($storedBytes / 1024 / 1024, 1) }} MB</p>
            </div>
        </div>

        <div class="mt-5 flex gap-3">
            <button type="button" wire:click="runNow('database')"
                    class="rounded-md bg-indigo-600 px-3.5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 transition">
                Back up database now
            </button>
            @if ($tenant)
                <button type="button" wire:click="runNow('files')"
                        class="rounded-md bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 ring-1 ring-inset ring-slate-300 hover:bg-slate-50 transition">
                    Back up files now
                </button>
            @endif
        </div>
    </div>

    <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-900/5">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Started</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Kind</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Size</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Destination</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Restore</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($backups as $backup)
                    <tr class="hover:bg-slate-50 transition">
                        <td class="px-4 py-3 text-sm text-slate-900">{{ $backup->startedAt?->format('Y-m-d H:i') }}</td>
                        <td class="px-4 py-3 text-sm text-slate-500">{{ $backup->kind }}</td>
                        <td class="px-4 py-3">
                            @if ($backup->status === 'ok')
                                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20">ok</span>
                            @elseif ($backup->status === 'failed')
                                <span class="inline-flex items-center rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-medium text-red-700 ring-1 ring-inset ring-red-600/20"
                                      title="{{ $backup->error }}">failed</span>
                            @elseif ($backup->status === 'running')
                                <span class="inline-flex items-center rounded-full bg-sky-50 px-2.5 py-0.5 text-xs font-medium text-sky-700 ring-1 ring-inset ring-sky-600/20">running</span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600 ring-1 ring-inset ring-slate-500/20">pruned</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-500">
                            {{ $backup->sizeBytes === null ? '—' : number_format($backup->sizeBytes / 1024 / 1024, 1) . ' MB' }}
                            @if ($backup->isEncrypted)
                                <span class="ml-1 text-xs text-slate-400" title="Encrypted at rest">🔒</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-xs font-mono text-slate-500">
                            {{ $backup->destinationPath ?? \Illuminate\Support\Str::limit($backup->error, 60) }}
                        </td>
                        <td class="px-4 py-3 text-xs font-mono text-slate-500">
                            @if ($backup->isRestorable() && $backup->kind === 'database' && $backup->tenantId)
                                {{-- Deliberately a command, not a button: a restore replaces live
                                     customer data and stays an operator procedure. --}}
                                <code class="select-all">backup:restore {{ $backup->id }} --actor=…</code>
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-sm text-slate-500">
                            Nothing recorded yet for this subject.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
