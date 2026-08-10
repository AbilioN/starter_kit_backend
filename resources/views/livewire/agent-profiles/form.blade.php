<div class="max-w-2xl">
    <div class="mb-8">
        <a href="{{ url('/god/agent-profiles') }}" class="text-sm font-medium text-slate-500 hover:text-slate-700">&larr; Agent Profiles</a>
        <h1 class="mt-2 text-2xl font-semibold tracking-tight text-slate-900">{{ $profileId ? 'Edit Agent Profile' : 'New Agent Profile' }}</h1>
    </div>

    <form wire:submit="save" class="space-y-8">
        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
            <h2 class="text-sm font-semibold text-slate-900">Details</h2>
            <div class="mt-4 grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label for="name" class="block text-sm font-medium text-slate-700">Name</label>
                    <input type="text" id="name" wire:model="name" required placeholder="e.g. Support Bot"
                           class="mt-1.5 block w-full rounded-md border-0 px-3 py-2 text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 text-sm">
                    @error('name') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="modelPreset" class="block text-sm font-medium text-slate-700">Model (optional)</label>
                    <select id="modelPreset" wire:model.live="modelPreset"
                            class="mt-1.5 block w-full rounded-md border-0 px-3 py-2 text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 text-sm">
                        <option value="">— inherit (BYOK/global default) —</option>
                        <option value="gpt-4o">gpt-4o</option>
                        <option value="gpt-4o-mini">gpt-4o-mini</option>
                        <option value="gpt-4-turbo">gpt-4-turbo</option>
                        <option value="gpt-4">gpt-4</option>
                        <option value="gpt-3.5-turbo">gpt-3.5-turbo</option>
                        <option value="custom">Custom…</option>
                    </select>

                    @if ($modelPreset === 'custom')
                        <input type="text" wire:model="modelCustom" placeholder="e.g. gpt-4o-2026-08-01"
                               class="mt-2 block w-full rounded-md border-0 px-3 py-2 text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 text-sm font-mono">
                        @error('modelCustom') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    @endif

                    <p class="mt-1.5 text-xs text-slate-500">Leave as "inherit" to use the tenant's own BYOK default (or the worker's global default).</p>
                </div>
                <div class="sm:col-span-2">
                    <label for="description" class="block text-sm font-medium text-slate-700">Description</label>
                    <input type="text" id="description" wire:model="description" placeholder="Shown to the tenant admin when picking an agent to chat with"
                           class="mt-1.5 block w-full rounded-md border-0 px-3 py-2 text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 text-sm">
                </div>
                <div class="sm:col-span-2">
                    <label for="avatarUpload" class="block text-sm font-medium text-slate-700">Avatar (optional)</label>
                    @if ($currentAvatar && ! $avatarUpload)
                        <img src="{{ asset('storage/'.$currentAvatar) }}" alt="Current avatar" class="mt-2 mb-2 h-12 w-12 rounded-full object-cover ring-1 ring-slate-200">
                    @endif
                    <input type="file" id="avatarUpload" wire:model="avatarUpload" accept="image/*"
                           class="mt-1.5 block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200">
                    <div wire:loading wire:target="avatarUpload" class="mt-1.5 text-xs text-slate-500">Uploading…</div>
                    @if ($avatarUpload)
                        <img src="{{ $avatarUpload->temporaryUrl() }}" alt="New avatar preview" class="mt-2 h-12 w-12 rounded-full object-cover ring-1 ring-slate-200">
                    @endif
                    @error('avatarUpload') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2">
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" wire:model="isActive"
                               class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-600">
                        Active
                    </label>
                </div>
            </div>
        </div>

        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
            <h2 class="text-sm font-semibold text-slate-900">Persona</h2>
            <p class="mt-1 text-sm text-slate-500">Read live from here on every message sent to this agent — editing it takes effect immediately for every tenant that already has it enabled, no re-sync needed.</p>
            <div class="mt-4">
                <label for="systemPrompt" class="block text-sm font-medium text-slate-700">System prompt (optional)</label>
                <textarea id="systemPrompt" wire:model="systemPrompt" rows="6" placeholder="You are a helpful support agent for {company}. Answer concisely and..."
                          class="mt-1.5 block w-full rounded-md border-0 px-3 py-2 text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 text-sm font-mono"></textarea>
                <p class="mt-1.5 text-xs text-slate-500">Leave blank to fall back to the tenant's own BYOK default prompt, or a generic prompt built from this agent's name/description.</p>
            </div>
        </div>

        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
            <h2 class="text-sm font-semibold text-slate-900">Available on plans</h2>
            <p class="mt-1 text-sm text-slate-500">Only tenants on a checked plan can chat with this agent. Changes here apply immediately to already-provisioned tenants.</p>
            <div class="mt-4 space-y-2">
                @forelse ($plans as $plan)
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" wire:model="selectedPlanIds" value="{{ $plan->id }}"
                               class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-600">
                        {{ $plan->name }}
                    </label>
                @empty
                    <p class="text-sm text-slate-500">No active subscription plans yet.</p>
                @endforelse
            </div>
        </div>

        <div class="flex justify-end gap-3">
            <a href="{{ url('/god/agent-profiles') }}"
               class="rounded-md px-3.5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-100 transition">Cancel</a>
            <button type="submit"
                    class="rounded-md bg-indigo-600 px-3.5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 transition">
                Save Agent Profile
            </button>
        </div>
    </form>
</div>
