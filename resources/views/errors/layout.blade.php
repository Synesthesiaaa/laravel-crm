<!DOCTYPE html>
<html lang="en" class="crm-interface">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <meta name="description" content="Private CRM error page for authenticated application workflows.">
    <title>@yield('title') — {{ config('app.name') }}</title>
    <script>
        (function () {
            var theme = 'light';
            try { theme = localStorage.getItem('theme') === 'dark' ? 'dark' : 'light'; } catch (_) {}
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
    @vite(['resources/css/app.css'])
    <style>
        body { background: var(--color-surface); color: var(--color-on-surface); font-family: var(--font-sans); display: flex; align-items: center; justify-content: center; min-height: 100dvh; margin: 0; }
        .error-page { text-align: center; padding: 2rem; max-width: 28rem; }
        .error-code { font-size: 4rem; font-weight: 600; color: var(--color-action); line-height: 1; letter-spacing: -.025em; }
        .error-title { font-size: 1.5rem; font-weight: 700; margin: .75rem 0 .5rem; }
        .error-desc { color: var(--color-on-surface-muted); margin-bottom: 2rem; font-size: .9375rem; }
    </style>
</head>
<body>
    <main class="error-page">
        <div class="error-code">@yield('code')</div>
        <h1 class="error-title">@yield('title')</h1>
        <p class="error-desc">@yield('description')</p>
        <a href="{{ url('/') }}" class="btn-primary">
            <x-icon name="arrow-left" class="w-4 h-4" aria-hidden="true" /> Go Home
        </a>
    </main>
</body>
</html>
