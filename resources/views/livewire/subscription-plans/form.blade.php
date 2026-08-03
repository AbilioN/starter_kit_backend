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
                    <label for="maxStorageMb" class="block text-sm font-medium text-slate-700">Max Storage (MB)</label>
                    <input type="number" id="maxStorageMb" wire:model="maxStorageMb" min="0"
                           class="mt-1.5 block w-full rounded-md border-0 px-3 py-2 text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 text-sm">
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
