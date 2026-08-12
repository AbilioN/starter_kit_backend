<div>
    <div class="mb-8">
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Security</h1>
        <p class="mt-1 text-sm text-slate-500">
            Two-factor authentication for your GodAdmin account. This account has unrestricted access to every tenant
            and to the landlord database, so a second factor is strongly recommended.
        </p>
    </div>

    @if ($status)
        <div class="mb-5 rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-800 ring-1 ring-emerald-600/10">
            {{ $status }}
        </div>
    @endif

    @if ($error)
        <div class="mb-5 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700 ring-1 ring-red-600/10">
            {{ $error }}
        </div>
    @endif

    {{-- Shown exactly once, right after enabling or regenerating. There is no
         way back to this list: the codes are stored hashed. --}}
    @if ($recoveryCodes)
        <div class="mb-6 rounded-xl bg-amber-50 p-6 ring-1 ring-amber-600/20">
            <h2 class="text-sm font-semibold text-amber-900">Save your recovery codes now</h2>
            <p class="mt-1 text-sm text-amber-800">
                Each code works once, and they are the only way back in if you lose your authenticator.
                <strong>They will not be shown again.</strong>
            </p>
            <div class="mt-4 grid grid-cols-2 gap-2 font-mono text-sm text-amber-900 sm:grid-cols-4">
                @foreach ($recoveryCodes as $code)
                    <div class="rounded bg-white/70 px-2.5 py-1.5 text-center ring-1 ring-amber-600/10">{{ $code }}</div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
        <div class="flex items-start justify-between gap-6">
            <div>
                <h2 class="text-sm font-semibold text-slate-900">Authenticator app (TOTP)</h2>
                <p class="mt-1 text-sm text-slate-500">
                    Works with Google Authenticator, 1Password, Authy or any other TOTP app.
                </p>
            </div>
            @if ($enabled)
                <span class="shrink-0 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 ring-1 ring-emerald-600/20">Enabled</span>
            @else
                <span class="shrink-0 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600 ring-1 ring-slate-500/20">Disabled</span>
            @endif
        </div>

        <div class="mt-6 border-t border-slate-100 pt-6">
            @if ($settingUp)
                <p class="text-sm text-slate-700">Scan this with your authenticator app, then enter the code it shows.</p>

                <div class="mt-4 flex flex-col gap-6 sm:flex-row sm:items-start">
                    <div class="shrink-0 rounded-lg bg-white p-3 ring-1 ring-slate-200">
                        {!! $qrCodeSvg !!}
                    </div>

                    <div class="flex-1">
                        <p class="text-xs text-slate-500">Can't scan? Enter this key manually:</p>
                        <code class="mt-1 block break-all rounded bg-slate-50 px-2.5 py-2 font-mono text-xs text-slate-800 ring-1 ring-slate-200">{{ $pendingSecret }}</code>

                        <form wire:submit="confirmSetup" class="mt-5 space-y-3">
                            <label for="confirmCode" class="block text-sm font-medium text-slate-700">Code from your app</label>
                            <input type="text" id="confirmCode" wire:model="confirmCode" required autofocus
                                   inputmode="numeric" autocomplete="one-time-code" placeholder="123456"
                                   class="block w-full max-w-xs rounded-md border-0 px-3 py-2 text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 text-sm tracking-widest">
                            @error('confirmCode') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

                            <div class="flex items-center gap-3">
                                <button type="submit"
                                        class="rounded-md bg-indigo-600 px-3.5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 transition"
                                        wire:loading.attr="disabled" wire:target="confirmSetup">
                                    Confirm and enable
                                </button>
                                <button type="button" wire:click="cancelSetup"
                                        class="text-sm text-slate-500 hover:text-slate-700">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>
            @elseif ($enabled)
                <div class="flex flex-wrap items-center gap-3">
                    <button wire:click="regenerateRecoveryCodes"
                            class="rounded-md bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 shadow-sm ring-1 ring-inset ring-slate-300 hover:bg-slate-50 transition">
                        Regenerate recovery codes
                    </button>
                </div>

                <form wire:submit="disable" class="mt-6 border-t border-slate-100 pt-6">
                    <h3 class="text-sm font-semibold text-slate-900">Disable two-factor authentication</h3>
                    <p class="mt-1 text-sm text-slate-500">Confirm your password to turn it off.</p>
                    <div class="mt-3 flex flex-wrap items-start gap-3">
                        <input type="password" wire:model="disablePassword" required placeholder="Current password"
                               class="block w-full max-w-xs rounded-md border-0 px-3 py-2 text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-red-600 text-sm">
                        <button type="submit"
                                class="rounded-md bg-red-600 px-3.5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-500 transition"
                                wire:loading.attr="disabled" wire:target="disable">
                            Disable
                        </button>
                    </div>
                    @error('disablePassword') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                </form>
            @else
                <button wire:click="startSetup"
                        class="rounded-md bg-indigo-600 px-3.5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 transition"
                        wire:loading.attr="disabled" wire:target="startSetup">
                    Enable two-factor authentication
                </button>
            @endif
        </div>
    </div>
</div>
