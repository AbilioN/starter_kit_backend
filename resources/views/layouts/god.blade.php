<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title ?? 'GodAdmin' }}</title>
    @livewireStyles
    <style>
        body { font-family: system-ui, sans-serif; margin: 0; background: #f4f4f5; color: #18181b; }
        header { background: #18181b; color: #fff; padding: 1rem 1.5rem; display: flex; justify-content: space-between; align-items: center; }
        header a { color: #fff; text-decoration: none; margin-right: 1rem; }
        main { padding: 1.5rem; max-width: 960px; margin: 0 auto; }
        table { width: 100%; border-collapse: collapse; background: #fff; }
        th, td { text-align: left; padding: 0.5rem 0.75rem; border-bottom: 1px solid #e4e4e7; }
        form { display: flex; flex-direction: column; gap: 0.75rem; max-width: 360px; }
        input, select, textarea { padding: 0.5rem; border: 1px solid #d4d4d8; border-radius: 4px; }
        button { padding: 0.5rem 1rem; background: #18181b; color: #fff; border: none; border-radius: 4px; cursor: pointer; }
        .error { color: #dc2626; font-size: 0.875rem; }
        .card { background: #fff; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; }
    </style>
</head>
<body>
    @auth('godadmin')
        <header>
            <div>
                <a href="{{ url('/god/dashboard') }}">Dashboard</a>
                <a href="{{ url('/god/tenants') }}">Tenants</a>
                <a href="{{ url('/god/subscription-plans') }}">Subscription Plans</a>
            </div>
            <form method="POST" action="{{ url('/god/logout') }}">
                @csrf
                <button type="submit">Log out</button>
            </form>
        </header>
    @endauth
    <main>
        {{ $slot }}
    </main>
    @livewireScripts
</body>
</html>
