<div class="max-w-xl">
    <div class="mb-8">
        <a href="{{ url('/god/tenants') }}" class="text-sm font-medium text-slate-500 hover:text-slate-700">&larr; Tenants</a>
        <h1 class="mt-2 text-2xl font-semibold tracking-tight text-slate-900">New Tenant</h1>
    </div>

    @if ($error)
        <div class="mb-5 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700 ring-1 ring-red-600/10">
            {{ $error }}
        </div>
    @endif

    <form wire:submit="save" class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5 space-y-5">
        <div>
            <label for="name" class="block text-sm font-medium text-slate-700">Name</label>
            <input type="text" id="name" wire:model="name" required
                   class="mt-1.5 block w-full rounded-md border-0 px-3 py-2 text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 text-sm">
            @error('name') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="subdomain" class="block text-sm font-medium text-slate-700">Subdomain</label>
            <div class="mt-1.5 flex rounded-md ring-1 ring-inset ring-slate-300 focus-within:ring-2 focus-within:ring-indigo-600">
                <input type="text" id="subdomain" wire:model="subdomain" required
                       class="block w-full rounded-l-md border-0 px-3 py-2 text-slate-900 focus:ring-0 text-sm">
                <span class="inline-flex items-center rounded-r-md px-3 text-sm text-slate-500 bg-slate-50">.starterkit.test</span>
            </div>
            @error('subdomain') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="planId" class="block text-sm font-medium text-slate-700">Subscription Plan</label>
            <select id="planId" wire:model="planId"
                    class="mt-1.5 block w-full rounded-md border-0 px-3 py-2 text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 text-sm">
                <option value="">— none —</option>
                @foreach ($plans as $plan)
                    <option value="{{ $plan->id }}">{{ $plan->name }}</option>
                @endforeach
            </select>
        </div>

        <hr class="border-slate-200">

        <div>
            <span class="block text-sm font-medium text-slate-700">Branding</span>
            <p class="mt-1 text-xs text-slate-500">Optional — can also be set later, or self-managed by the tenant owner.</p>

            <div class="mt-3 flex items-center gap-6">
                <div>
                    <label for="themePrimaryColor" class="block text-xs font-medium text-slate-500">Primary color</label>
                    <input type="color" id="themePrimaryColor" wire:model="themePrimaryColor"
                           class="mt-1 h-9 w-16 rounded-md border-0 ring-1 ring-inset ring-slate-300 cursor-pointer">
                </div>
                <div>
                    <label for="themeSecondaryColor" class="block text-xs font-medium text-slate-500">Secondary color</label>
                    <input type="color" id="themeSecondaryColor" wire:model="themeSecondaryColor"
                           class="mt-1 h-9 w-16 rounded-md border-0 ring-1 ring-inset ring-slate-300 cursor-pointer">
                </div>
            </div>

            <div class="mt-4">
                <label for="logo" class="block text-xs font-medium text-slate-500">Logo</label>
                <input type="file" id="logo" wire:model="logo" accept="image/*"
                       class="mt-1.5 block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200">
                <div wire:loading wire:target="logo" class="mt-1.5 text-xs text-slate-500">Uploading…</div>
                @if ($logo)
                    <img src="{{ $logo->temporaryUrl() }}" alt="Logo preview" class="mt-2 h-12 w-auto rounded ring-1 ring-slate-200">
                @endif
                @error('logo') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <hr class="border-slate-200">

        <div>
            <label for="adminEmail" class="block text-sm font-medium text-slate-700">Owner Admin Email</label>
            <input type="email" id="adminEmail" wire:model="adminEmail" required
                   class="mt-1.5 block w-full rounded-md border-0 px-3 py-2 text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 text-sm">
            @error('adminEmail') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="adminPassword" class="block text-sm font-medium text-slate-700">Owner Admin Password</label>
            <input type="password" id="adminPassword" wire:model="adminPassword" required
                   class="mt-1.5 block w-full rounded-md border-0 px-3 py-2 text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 text-sm">
            @error('adminPassword') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="flex justify-end gap-3 pt-2">
            <a href="{{ url('/god/tenants') }}"
               class="rounded-md px-3.5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-100 transition">Cancel</a>
            <button type="submit"
                    class="rounded-md bg-indigo-600 px-3.5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 transition"
                    wire:loading.attr="disabled" wire:target="save">
                <span wire:loading.remove wire:target="save">Create Tenant</span>
                <span wire:loading wire:target="save">Provisioning…</span>
            </button>
        </div>
    </form>
</div>
