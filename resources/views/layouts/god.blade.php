<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'GodAdmin' }}</title>
    @vite(['resources/css/app.css'])
    @livewireStyles
</head>
<body class="h-full font-sans antialiased text-slate-900">
    @auth('godadmin')
        <div class="min-h-full">
            <header class="bg-slate-900">
                <nav class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                    <div class="flex h-16 items-center justify-between">
                        <div class="flex items-center gap-8">
                            <a href="{{ url('/god/dashboard') }}" class="flex items-center gap-2 text-white font-semibold tracking-tight">
                                <span class="inline-flex h-7 w-7 items-center justify-center rounded-md bg-indigo-500 text-sm">⚡</span>
                                GodAdmin
                            </a>
                            <div class="hidden sm:flex sm:gap-1">
                                <a href="{{ url('/god/dashboard') }}"
                                   class="rounded-md px-3 py-2 text-sm font-medium text-slate-300 hover:bg-slate-800 hover:text-white transition">Dashboard</a>
                                <a href="{{ url('/god/tenants') }}"
                                   class="rounded-md px-3 py-2 text-sm font-medium text-slate-300 hover:bg-slate-800 hover:text-white transition">Tenants</a>
                                <a href="{{ url('/god/subscription-plans') }}"
                                   class="rounded-md px-3 py-2 text-sm font-medium text-slate-300 hover:bg-slate-800 hover:text-white transition">Subscription Plans</a>
                                <a href="{{ url('/god/financial-report') }}"
                                   class="rounded-md px-3 py-2 text-sm font-medium text-slate-300 hover:bg-slate-800 hover:text-white transition">Financial Report</a>
                            </div>
                        </div>
                        <form method="POST" action="{{ url('/god/logout') }}">
                            @csrf
                            <button type="submit"
                                    class="rounded-md bg-slate-800 px-3 py-2 text-sm font-medium text-slate-200 hover:bg-slate-700 transition">
                                Log out
                            </button>
                        </form>
                    </div>
                </nav>
            </header>
            <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
                {{ $slot }}
            </main>
        </div>
    @else
        <main class="flex min-h-full items-center justify-center px-4 py-12">
            {{ $slot }}
        </main>
    @endauth
    @livewireScripts
</body>
</html>
