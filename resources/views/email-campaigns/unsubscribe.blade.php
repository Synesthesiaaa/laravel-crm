<!doctype html>
<html lang="en" class="crm-interface">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Unsubscribe from emails</title>
    <meta name="robots" content="noindex,nofollow,noarchive">
    <script>
        (function () {
            var theme = 'light';
            try { theme = localStorage.getItem('theme') === 'dark' ? 'dark' : 'light'; } catch (_) {}
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen px-6 py-16">
    <main class="mx-auto max-w-lg space-y-6">
    <h1 class="text-2xl font-semibold tracking-tight">Unsubscribe from emails</h1>
    <p class="text-[var(--color-on-surface-muted)]">Stop receiving future campaign emails at <strong class="break-all text-[var(--color-on-surface)]">{{ $recipient->email }}</strong>?</p>
    <form method="POST" action="{{ request()->fullUrl() }}">
        @csrf
        <button type="submit" class="btn-primary">Unsubscribe</button>
    </form>
    </main>
</body>
</html>
