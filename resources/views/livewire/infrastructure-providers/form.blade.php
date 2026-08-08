<div class="max-w-2xl">
    <div class="mb-8">
        <a href="{{ url('/god/infrastructure-providers') }}" class="text-sm font-medium text-slate-500 hover:text-slate-700">&larr; Infrastructure Providers</a>
        <h1 class="mt-2 text-2xl font-semibold tracking-tight text-slate-900">{{ $providerId ? 'Edit Provider' : 'New Provider' }}</h1>
    </div>

    <form wire:submit="save" class="space-y-8">
        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
            <h2 class="text-sm font-semibold text-slate-900">Details</h2>
            <div class="mt-4 grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label for="type" class="block text-sm font-medium text-slate-700">Type</label>
                    <select id="type" wire:model.live="type" {{ $providerId ? 'disabled' : '' }}
                            class="mt-1.5 block w-full rounded-md border-0 px-3 py-2 text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 text-sm disabled:bg-slate-50 disabled:text-slate-500">
                        <option value="broadcasting">Broadcasting (Pusher)</option>
                        <option value="storage">Storage (S3)</option>
                    </select>
                    @if ($providerId)
                        <p class="mt-1.5 text-xs text-slate-500">Type can't be changed after creation.</p>
                    @endif
                </div>

                <div>
                    <label for="name" class="block text-sm font-medium text-slate-700">Name</label>
                    <input type="text" id="name" wire:model="name" required placeholder="e.g. Acme Dedicated S3"
                           class="mt-1.5 block w-full rounded-md border-0 px-3 py-2 text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 text-sm">
                    @error('name') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
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

        @if ($type === 'broadcasting')
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
                <h2 class="text-sm font-semibold text-slate-900">Pusher App Credentials</h2>
                <div class="mt-4 grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <div>
                        <label for="configAppId" class="block text-sm font-medium text-slate-700">App ID</label>
                        <input type="text" id="configAppId" wire:model="configAppId"
                               class="mt-1.5 block w-full rounded-md border-0 px-3 py-2 text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 text-sm font-mono">
                        @error('configAppId') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="configCluster" class="block text-sm font-medium text-slate-700">Cluster</label>
                        <input type="text" id="configCluster" wire:model="configCluster" placeholder="eu, us2, ..."
                               class="mt-1.5 block w-full rounded-md border-0 px-3 py-2 text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 text-sm font-mono">
                        @error('configCluster') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="configKey" class="block text-sm font-medium text-slate-700">Key</label>
                        <input type="text" id="configKey" wire:model="configKey"
                               class="mt-1.5 block w-full rounded-md border-0 px-3 py-2 text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 text-sm font-mono">
                        @error('configKey') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="configSecret" class="block text-sm font-medium text-slate-700">Secret</label>
                        <input type="password" id="configSecret" wire:model="configSecret" autocomplete="new-password"
                               class="mt-1.5 block w-full rounded-md border-0 px-3 py-2 text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 text-sm font-mono">
                        @error('configSecret') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label for="configHost" class="block text-sm font-medium text-slate-700">Host (optional)</label>
                        <input type="text" id="configHost" wire:model="configHost" placeholder="defaults to api-{cluster}.pusher.com"
                               class="mt-1.5 block w-full rounded-md border-0 px-3 py-2 text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 text-sm font-mono">
                    </div>
                </div>
            </div>
        @else
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
                <h2 class="text-sm font-semibold text-slate-900">S3-Compatible Storage Credentials</h2>
                <div class="mt-4 grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <div>
                        <label for="configBucket" class="block text-sm font-medium text-slate-700">Bucket</label>
                        <input type="text" id="configBucket" wire:model="configBucket"
                               class="mt-1.5 block w-full rounded-md border-0 px-3 py-2 text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 text-sm font-mono">
                        @error('configBucket') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="configRegion" class="block text-sm font-medium text-slate-700">Region</label>
                        <input type="text" id="configRegion" wire:model="configRegion" placeholder="us-east-1"
                               class="mt-1.5 block w-full rounded-md border-0 px-3 py-2 text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 text-sm font-mono">
                    </div>
                    <div>
                        <label for="configKey" class="block text-sm font-medium text-slate-700">Access Key</label>
                        <input type="text" id="configKey" wire:model="configKey"
                               class="mt-1.5 block w-full rounded-md border-0 px-3 py-2 text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 text-sm font-mono">
                        @error('configKey') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="configSecret" class="block text-sm font-medium text-slate-700">Secret Key</label>
                        <input type="password" id="configSecret" wire:model="configSecret" autocomplete="new-password"
                               class="mt-1.5 block w-full rounded-md border-0 px-3 py-2 text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 text-sm font-mono">
                        @error('configSecret') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="configEndpoint" class="block text-sm font-medium text-slate-700">Endpoint (optional)</label>
                        <input type="text" id="configEndpoint" wire:model="configEndpoint" placeholder="for S3-compatible providers (DigitalOcean Spaces, MinIO, ...)"
                               class="mt-1.5 block w-full rounded-md border-0 px-3 py-2 text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 text-sm font-mono">
                    </div>
                    <div>
                        <label for="configUrl" class="block text-sm font-medium text-slate-700">Public URL (optional)</label>
                        <input type="text" id="configUrl" wire:model="configUrl"
                               class="mt-1.5 block w-full rounded-md border-0 px-3 py-2 text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 text-sm font-mono">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            <input type="checkbox" wire:model="configUsePathStyle"
                                   class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-600">
                            Use path-style endpoint
                        </label>
                    </div>
                </div>
            </div>
        @endif

        <div class="flex justify-end gap-3">
            <a href="{{ url('/god/infrastructure-providers') }}"
               class="rounded-md px-3.5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-100 transition">Cancel</a>
            <button type="submit"
                    class="rounded-md bg-indigo-600 px-3.5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 transition">
                Save Provider
            </button>
        </div>
    </form>
</div>
