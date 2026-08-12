<div class="w-full max-w-sm">
    <div class="mb-8 text-center">
        <span class="mx-auto inline-flex h-12 w-12 items-center justify-center rounded-xl bg-indigo-600 text-2xl text-white shadow-lg shadow-indigo-600/30">⚡</span>
        <h1 class="mt-4 text-xl font-semibold tracking-tight text-slate-900">GodAdmin</h1>
        <p class="mt-1 text-sm text-slate-500">Sign in to manage tenants and plans</p>
    </div>

    <div class="rounded-xl bg-white p-8 shadow-sm ring-1 ring-slate-900/5">
        @if ($error)
            <div class="mb-5 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700 ring-1 ring-red-600/10">
                {{ $error }}
            </div>
        @endif

        @if ($awaitingTwoFactor)
            <form wire:submit="verifyTwoFactor" class="space-y-5">
                <div>
                    <label for="code" class="block text-sm font-medium text-slate-700">Authentication code</label>
                    <p class="mt-1 text-xs text-slate-500">
                        Open your authenticator app and enter the 6-digit code, or use one of your recovery codes.
                    </p>
                    {{-- inputmode/autocomplete let phones show a numeric keypad and
                         offer the code straight from the OS autofill --}}
                    <input type="text" id="code" wire:model="code" required autofocus
                           inputmode="numeric" autocomplete="one-time-code" placeholder="123456"
                           class="mt-1.5 block w-full rounded-md border-0 px-3 py-2 text-slate-900 ring-1 ring-inset ring-slate-300 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 text-sm tracking-widest">
                    @error('code') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <button type="submit"
                        class="flex w-full justify-center rounded-md bg-indigo-600 px-3 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 transition"
                        wire:loading.attr="disabled" wire:target="verifyTwoFactor">
                    <span wire:loading.remove wire:target="verifyTwoFactor">Verify</span>
                    <span wire:loading wire:target="verifyTwoFactor">Verifying…</span>
                </button>

                <button type="button" wire:click="backToLogin"
                        class="w-full text-center text-sm text-slate-500 hover:text-slate-700">
                    Back to sign in
                </button>
            </form>
        @else
            <form wire:submit="login" class="space-y-5">
                <div>
                    <label for="email" class="block text-sm font-medium text-slate-700">Email</label>
                    <input type="email" id="email" wire:model="email" required autofocus
                           class="mt-1.5 block w-full rounded-md border-0 px-3 py-2 text-slate-900 ring-1 ring-inset ring-slate-300 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 text-sm">
                    @error('email') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-slate-700">Password</label>
                    <input type="password" id="password" wire:model="password" required
                           class="mt-1.5 block w-full rounded-md border-0 px-3 py-2 text-slate-900 ring-1 ring-inset ring-slate-300 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 text-sm">
                    @error('password') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <button type="submit"
                        class="flex w-full justify-center rounded-md bg-indigo-600 px-3 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 transition"
                        wire:loading.attr="disabled" wire:target="login">
                    <span wire:loading.remove wire:target="login">Log in</span>
                    <span wire:loading wire:target="login">Signing in…</span>
                </button>
            </form>
        @endif
    </div>
</div>
