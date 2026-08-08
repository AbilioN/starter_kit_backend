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
            <div class="grid grid-cols-3 gap-4 px-6 py-4">
                <dt class="text-sm font-medium text-slate-500">Subscription Plan</dt>
                <dd class="col-span-2 text-sm text-slate-900">
                    @if ($plan)
                        {{ $plan->name }}
                        @if ($plan->isPublic)
                            <span class="ml-2 inline-flex items-center rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700 ring-1 ring-inset ring-indigo-600/20">Public</span>
                        @else
                            <span class="ml-2 inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600 ring-1 ring-inset ring-slate-500/10">Private</span>
                        @endif
                    @else
                        <span class="text-slate-400">— none —</span>
                    @endif
                </dd>
            </div>
        </dl>
    </div>

    <div class="mt-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
        <h2 class="text-sm font-semibold text-slate-900">Subscription Plan</h2>
        <p class="mt-1 text-xs text-slate-500">
            Change this tenant's plan directly — records a mock payment and re-syncs their feature flags/limits immediately, same as when the tenant owner changes it themselves.
        </p>

        @if ($planSaved)
            <div class="mt-4 rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-700 ring-1 ring-emerald-600/10">
                {{ $planSaved }}
            </div>
        @endif
        @if ($planError)
            <div class="mt-4 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700 ring-1 ring-red-600/10">
                {{ $planError }}
            </div>
        @endif

        <form wire:submit="savePlan" class="mt-4 flex items-end gap-3">
            <div class="flex-1">
                <label for="selectedPlanId" class="block text-xs font-medium text-slate-500">Plan</label>
                <select id="selectedPlanId" wire:model="selectedPlanId"
                        class="mt-1.5 block w-full rounded-md border-0 px-3 py-2 text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 text-sm">
                    <option value="">— select a plan —</option>
                    @foreach ($plans as $planOption)
                        <option value="{{ $planOption->id }}">{{ $planOption->name }}{{ $planOption->isPublic ? '' : ' (private)' }}</option>
                    @endforeach
                </select>
                @error('selectedPlanId') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <button type="submit"
                    class="rounded-md bg-indigo-600 px-3.5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 transition"
                    wire:loading.attr="disabled" wire:target="savePlan">
                <span wire:loading.remove wire:target="savePlan">Change Plan</span>
                <span wire:loading wire:target="savePlan">Saving…</span>
            </button>
        </form>
    </div>

    <div class="mt-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
        <h2 class="text-sm font-semibold text-slate-900">Infrastructure Overrides</h2>
        <p class="mt-1 text-xs text-slate-500">
            Leave unset to inherit the plan's default (or the global config if the plan has none either). Only needed for a tenant that needs its own dedicated Pusher app or storage bucket.
        </p>

        @if ($infraSaved)
            <div class="mt-4 rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-700 ring-1 ring-emerald-600/10">
                {{ $infraSaved }}
            </div>
        @endif

        <form wire:submit="saveInfrastructure" class="mt-4 space-y-4">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="broadcastingProviderId" class="block text-xs font-medium text-slate-500">Broadcasting Provider</label>
                    <select id="broadcastingProviderId" wire:model="broadcastingProviderId"
                            class="mt-1.5 block w-full rounded-md border-0 px-3 py-2 text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 text-sm">
                        <option value="">— inherit from plan —</option>
                        @foreach ($broadcastingProviders as $provider)
                            <option value="{{ $provider->id }}">{{ $provider->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="storageProviderId" class="block text-xs font-medium text-slate-500">Storage Provider</label>
                    <select id="storageProviderId" wire:model="storageProviderId"
                            class="mt-1.5 block w-full rounded-md border-0 px-3 py-2 text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 text-sm">
                        <option value="">— inherit from plan —</option>
                        @foreach ($storageProviders as $provider)
                            <option value="{{ $provider->id }}">{{ $provider->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="flex justify-end">
                <button type="submit"
                        class="rounded-md bg-indigo-600 px-3.5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 transition"
                        wire:loading.attr="disabled" wire:target="saveInfrastructure">
                    <span wire:loading.remove wire:target="saveInfrastructure">Save Overrides</span>
                    <span wire:loading wire:target="saveInfrastructure">Saving…</span>
                </button>
            </div>
        </form>
    </div>

    <div class="mt-6 flex justify-end">
        <button wire:click="toggleStatus" wire:confirm="Are you sure?"
                class="rounded-md px-3.5 py-2.5 text-sm font-semibold shadow-sm transition
                       {{ $tenant->isActive() ? 'bg-red-600 text-white hover:bg-red-500' : 'bg-emerald-600 text-white hover:bg-emerald-500' }}">
            {{ $tenant->isActive() ? 'Suspend Tenant' : 'Reactivate Tenant' }}
        </button>
    </div>

    <div class="mt-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
        <h2 class="text-sm font-semibold text-slate-900">Branding</h2>
        <p class="mt-1 text-xs text-slate-500">
            The tenant owner can also manage this from their own admin panel — whichever was set most recently wins.
        </p>

        @if ($brandingSaved)
            <div class="mt-4 rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-700 ring-1 ring-emerald-600/10">
                {{ $brandingSaved }}
            </div>
        @endif

        <form wire:submit="saveBranding" class="mt-4 space-y-4">
            <div class="flex items-center gap-6">
                <div>
                    <label for="show-themePrimaryColor" class="block text-xs font-medium text-slate-500">Primary color</label>
                    <input type="color" id="show-themePrimaryColor" wire:model="themePrimaryColor"
                           class="mt-1 h-9 w-16 rounded-md border-0 ring-1 ring-inset ring-slate-300 cursor-pointer">
                </div>
                <div>
                    <label for="show-themeSecondaryColor" class="block text-xs font-medium text-slate-500">Secondary color</label>
                    <input type="color" id="show-themeSecondaryColor" wire:model="themeSecondaryColor"
                           class="mt-1 h-9 w-16 rounded-md border-0 ring-1 ring-inset ring-slate-300 cursor-pointer">
                </div>
            </div>

            <div>
                <label for="show-logo" class="block text-xs font-medium text-slate-500">Logo</label>
                @if ($tenant->logoPath && ! $logo)
                    <img src="{{ asset('storage/'.$tenant->logoPath) }}" alt="Current logo" class="mt-2 mb-2 h-12 w-auto rounded ring-1 ring-slate-200">
                @endif
                <input type="file" id="show-logo" wire:model="logo" accept="image/*"
                       class="mt-1.5 block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200">
                <div wire:loading wire:target="logo" class="mt-1.5 text-xs text-slate-500">Uploading…</div>
                @if ($logo)
                    <img src="{{ $logo->temporaryUrl() }}" alt="New logo preview" class="mt-2 h-12 w-auto rounded ring-1 ring-slate-200">
                @endif
                @error('logo') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="flex justify-end">
                <button type="submit"
                        class="rounded-md bg-indigo-600 px-3.5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 transition"
                        wire:loading.attr="disabled" wire:target="saveBranding">
                    <span wire:loading.remove wire:target="saveBranding">Save Branding</span>
                    <span wire:loading wire:target="saveBranding">Saving…</span>
                </button>
            </div>
        </form>
    </div>
</div>
