<div class="max-w-2xl">
    <div class="mb-8">
        <a href="{{ url('/god/subscription-plans') }}" class="text-sm font-medium text-slate-500 hover:text-slate-700">&larr; Subscription Plans</a>
        <h1 class="mt-2 text-2xl font-semibold tracking-tight text-slate-900">{{ $planId ? 'Edit Plan' : 'New Plan' }}</h1>
    </div>

    <form wire:submit="save" class="space-y-8">
        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
            <h2 class="text-sm font-semibold text-slate-900">Details</h2>
            <div class="mt-4 grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="name" class="block text-sm font-medium text-slate-700">Name</label>
                    <input type="text" id="name" wire:model="name" required
                           class="mt-1.5 block w-full rounded-md border-0 px-3 py-2 text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 text-sm">
                    @error('name') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="slug" class="block text-sm font-medium text-slate-700">Slug</label>
                    <input type="text" id="slug" wire:model="slug" required {{ $planId ? 'readonly' : '' }}
                           class="mt-1.5 block w-full rounded-md border-0 px-3 py-2 text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 text-sm read-only:bg-slate-50 read-only:text-slate-500 font-mono">
                    @error('slug') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="priceCents" class="block text-sm font-medium text-slate-700">Price (cents)</label>
                    <input type="number" id="priceCents" wire:model="priceCents" min="0"
                           class="mt-1.5 block w-full rounded-md border-0 px-3 py-2 text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 text-sm">
                    @error('priceCents') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
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
            <h2 class="text-sm font-semibold text-slate-900">Visibility</h2>
            <p class="mt-1 text-sm text-slate-500">Public plans are listed on the public pricing page and can be self-selected by a visitor. Private plans are never listed — only a GodAdmin can assign them to a specific tenant.</p>
            <div class="mt-4 flex gap-6">
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="radio" wire:model="isPublic" value="1"
                           class="border-slate-300 text-indigo-600 focus:ring-indigo-600">
                    Public
                </label>
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="radio" wire:model="isPublic" value="0"
                           class="border-slate-300 text-indigo-600 focus:ring-indigo-600">
                    Private
                </label>
            </div>
        </div>

        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
            <h2 class="text-sm font-semibold text-slate-900">Marketing</h2>
            <p class="mt-1 text-sm text-slate-500">Used on the public pricing page only — has no effect on tenant branding.</p>
            <div class="mt-4 flex items-start gap-8">
                <div>
                    <label for="tertiaryColor" class="block text-xs font-medium text-slate-500">Accent color</label>
                    <input type="color" id="tertiaryColor" wire:model="tertiaryColor" value="{{ $tertiaryColor ?? '#6366F1' }}"
                           class="mt-1 h-9 w-16 rounded-md border-0 ring-1 ring-inset ring-slate-300 cursor-pointer">
                    @error('tertiaryColor') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="flex-1">
                    <label for="iconUpload" class="block text-xs font-medium text-slate-500">Icon (auto-generated in 3 sizes)</label>
                    <input type="file" id="iconUpload" wire:model="iconUpload" accept="image/*"
                           class="mt-1.5 block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200">
                    <div wire:loading wire:target="iconUpload" class="mt-1.5 text-xs text-slate-500">Uploading…</div>
                    @error('iconUpload') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror

                    @if ($iconUpload)
                        <div class="mt-2">
                            <p class="text-xs text-slate-500">New icon preview (will be resized to small/medium/large on save):</p>
                            <img src="{{ $iconUpload->temporaryUrl() }}" alt="Icon preview" class="mt-1 h-12 w-12 rounded ring-1 ring-slate-200 object-cover">
                        </div>
                    @elseif (!empty($iconPaths))
                        <div class="mt-2 flex items-end gap-3">
                            @foreach (['small', 'medium', 'large'] as $size)
                                @if (!empty($iconPaths[$size]))
                                    <div class="text-center">
                                        <img src="{{ asset('storage/'.$iconPaths[$size]) }}" alt="{{ $size }} icon" class="rounded ring-1 ring-slate-200 object-cover" style="width: {{ $size === 'small' ? 24 : ($size === 'medium' ? 40 : 56) }}px; height: {{ $size === 'small' ? 24 : ($size === 'medium' ? 40 : 56) }}px;">
                                        <span class="mt-1 block text-[10px] uppercase tracking-wide text-slate-400">{{ $size }}</span>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
            <h2 class="text-sm font-semibold text-slate-900">Features</h2>
            <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" wire:model="featureChat" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-600">
                    Chat
                </label>
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" wire:model="featureFileUpload" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-600">
                    File Upload
                </label>
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" wire:model="featureNotifications" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-600">
                    Notifications
                </label>
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" wire:model="featureAiAgent" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-600">
                    AI Agent
                </label>
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" wire:model="featureBackup" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-600">
                    Backups
                </label>
            </div>
        </div>

        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
            <h2 class="text-sm font-semibold text-slate-900">Limits</h2>
            <div class="mt-4 grid grid-cols-1 gap-5 sm:grid-cols-3">
                <div>
                    <label for="maxAdmins" class="block text-sm font-medium text-slate-700">Max Admins</label>
                    <input type="number" id="maxAdmins" wire:model="maxAdmins" min="0"
                           class="mt-1.5 block w-full rounded-md border-0 px-3 py-2 text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 text-sm">
                </div>
                <div>
                    <label for="maxUsers" class="block text-sm font-medium text-slate-700">Max Users</label>
                    <input type="number" id="maxUsers" wire:model="maxUsers" min="0"
                           class="mt-1.5 block w-full rounded-md border-0 px-3 py-2 text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 text-sm">
                </div>
                <div>
                    <label for="maxStoragePreset" class="block text-sm font-medium text-slate-700">Max Storage</label>
                    <select id="maxStoragePreset" wire:model.live="maxStoragePreset"
                            class="mt-1.5 block w-full rounded-md border-0 px-3 py-2 text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 text-sm">
                        <option value="256">256 MB</option>
                        <option value="512">512 MB</option>
                        <option value="1024">1 GB</option>
                        <option value="2048">2 GB</option>
                        <option value="5120">5 GB</option>
                        <option value="10240">10 GB</option>
                        <option value="51200">50 GB</option>
                        <option value="102400">100 GB</option>
                        <option value="unlimited">Unlimited</option>
                        <option value="custom">Custom…</option>
                    </select>

                    @if ($maxStoragePreset === 'custom')
                        <input type="number" wire:model="maxStorageMbCustom" min="1" placeholder="MB"
                               class="mt-2 block w-full rounded-md border-0 px-3 py-2 text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 text-sm">
                        @error('maxStorageMbCustom') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    @endif
                </div>
            </div>
        </div>

        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
            <h2 class="text-sm font-semibold text-slate-900">Infrastructure</h2>
            <p class="mt-1 text-sm text-slate-500">Optional. Tenants on this plan use these by default, unless a tenant has its own override (set on the tenant's page). Leave unset to use the global config.</p>
            <div class="mt-4 grid grid-cols-1 gap-5 sm:grid-cols-3">
                <div>
                    <label for="broadcastingProviderId" class="block text-sm font-medium text-slate-700">Broadcasting Provider</label>
                    <select id="broadcastingProviderId" wire:model="broadcastingProviderId"
                            class="mt-1.5 block w-full rounded-md border-0 px-3 py-2 text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 text-sm">
                        <option value="">— global default —</option>
                        @foreach ($broadcastingProviders as $provider)
                            <option value="{{ $provider->id }}">{{ $provider->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="storageProviderId" class="block text-sm font-medium text-slate-700">Storage Provider</label>
                    <select id="storageProviderId" wire:model="storageProviderId"
                            class="mt-1.5 block w-full rounded-md border-0 px-3 py-2 text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 text-sm">
                        <option value="">— global default —</option>
                        @foreach ($storageProviders as $provider)
                            <option value="{{ $provider->id }}">{{ $provider->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="aiProviderId" class="block text-sm font-medium text-slate-700">AI Provider</label>
                    <select id="aiProviderId" wire:model="aiProviderId"
                            class="mt-1.5 block w-full rounded-md border-0 px-3 py-2 text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 text-sm">
                        <option value="">— global default —</option>
                        @foreach ($aiProviders as $provider)
                            <option value="{{ $provider->id }}">{{ $provider->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="backupProviderId" class="block text-sm font-medium text-slate-700">Backup Destination</label>
                    <select id="backupProviderId" wire:model="backupProviderId"
                            class="mt-1.5 block w-full rounded-md border-0 px-3 py-2 text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 text-sm">
                        <option value="">— global default —</option>
                        @foreach ($backupProviders as $provider)
                            <option value="{{ $provider->id }}">{{ $provider->name }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1.5 text-xs text-slate-500">
                        "Global default" means the <code>BACKUP_*</code> disk, not "no backup".
                    </p>
                </div>
            </div>
        </div>

        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
            <h2 class="text-sm font-semibold text-slate-900">Backup Policy</h2>
            <p class="mt-1 text-xs text-slate-500">
                Read straight from this plan by the scheduler. Changing it takes effect on the next run — there is
                nothing to re-sync into the tenant.
            </p>
            <div class="mt-4 grid grid-cols-1 gap-5 sm:grid-cols-3">
                <div>
                    <label for="backupFrequencyHours" class="block text-sm font-medium text-slate-700">Frequency (hours)</label>
                    <input type="number" id="backupFrequencyHours" wire:model="backupFrequencyHours" min="1" placeholder="24"
                           class="mt-1.5 block w-full rounded-md border-0 px-3 py-2 text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 text-sm">
                    <p class="mt-1.5 text-xs text-slate-500">24 = daily, 168 = weekly. Blank = never.</p>
                    @error('backupFrequencyHours') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="backupRetentionDays" class="block text-sm font-medium text-slate-700">Retention (days)</label>
                    <input type="number" id="backupRetentionDays" wire:model="backupRetentionDays" min="1"
                           class="mt-1.5 block w-full rounded-md border-0 px-3 py-2 text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 text-sm">
                    <p class="mt-1.5 text-xs text-slate-500">The most recent backup is never deleted, whatever this says.</p>
                    @error('backupRetentionDays') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="backupMaxTotalMb" class="block text-sm font-medium text-slate-700">Capacity (MB)</label>
                    <input type="number" id="backupMaxTotalMb" wire:model="backupMaxTotalMb" min="1" placeholder="10240"
                           class="mt-1.5 block w-full rounded-md border-0 px-3 py-2 text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 text-sm">
                    <p class="mt-1.5 text-xs text-slate-500">Blank = uncapped. Over the cap, the run fails loudly rather than skipping.</p>
                    @error('backupMaxTotalMb') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-3">
            <a href="{{ url('/god/subscription-plans') }}"
               class="rounded-md px-3.5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-100 transition">Cancel</a>
            <button type="submit"
                    class="rounded-md bg-indigo-600 px-3.5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 transition">
                Save Plan
            </button>
        </div>
    </form>
</div>
