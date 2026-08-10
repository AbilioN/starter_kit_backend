<div>
    <div class="mb-8 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Agent Profiles</h1>
            <p class="mt-1 text-sm text-slate-500">AI agent personas (name, system prompt, model) — assign one to a subscription plan to make it available for tenants on that plan to chat with.</p>
        </div>
        <a href="{{ url('/god/agent-profiles/create') }}"
           class="rounded-md bg-indigo-600 px-3.5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 transition">
            + New Agent Profile
        </a>
    </div>

    <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-900/5">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Name</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Model</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($profiles as $profile)
                    <tr class="hover:bg-slate-50 transition">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                @if ($profile->avatar)
                                    <img src="{{ asset('storage/'.$profile->avatar) }}" alt="" class="h-8 w-8 rounded-full object-cover ring-1 ring-slate-200">
                                @else
                                    <div class="h-8 w-8 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center text-xs font-semibold">
                                        {{ strtoupper(substr($profile->name, 0, 1)) }}
                                    </div>
                                @endif
                                <div>
                                    <div class="text-sm font-medium text-slate-900">{{ $profile->name }}</div>
                                    @if ($profile->description)
                                        <div class="text-xs text-slate-500">{{ $profile->description }}</div>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-500 font-mono">{{ $profile->model ?: '—' }}</td>
                        <td class="px-4 py-3">
                            @if ($profile->isActive)
                                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20">Active</span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600 ring-1 ring-inset ring-slate-500/10">Inactive</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ url('/god/agent-profiles/'.$profile->id.'/edit') }}"
                               class="text-sm font-medium text-indigo-600 hover:text-indigo-500">Edit</a>
                            <button wire:click="delete('{{ $profile->id }}')" wire:confirm="Delete this agent profile? Tenants that had it enabled lose access to it (their chat history is kept)."
                                    class="ml-4 text-sm font-medium text-red-600 hover:text-red-500">Delete</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-10 text-center text-sm text-slate-500">No agent profiles yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
