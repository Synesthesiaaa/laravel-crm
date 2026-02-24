<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title') — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css'])
    <style>
        body { background: var(--color-surface); color: var(--color-on-surface); font-family: 'DM Sans', ui-sans-serif, system-ui, sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
        .error-page { text-align: center; padding: 2rem; max-width: 28rem; }
        .error-code { font-size: 6rem; font-weight: 800; color: var(--color-primary); line-height: 1; letter-spacing: -.05em; }
        .error-title { font-size: 1.5rem; font-weight: 700; margin: .75rem 0 .5rem; }
        .error-desc { color: var(--color-on-surface-muted); margin-bottom: 2rem; font-size: .9375rem; }
    </style>
</head>
<body>
    <div class="error-page">
        <div class="error-code">@yield('code')</div>
        <h1 class="error-title">@yield('title')</h1>
        <p class="error-desc">@yield('description')</p>
        <a href="{{ url('/') }}" style="display:inline-flex;align-items:center;gap:.5rem;padding:.625rem 1.25rem;background:var(--color-primary);color:#fff;border-radius:8px;font-weight:600;text-decoration:none;font-size:.875rem;">
            ← Go Home
        </a>
    </div>
</body>
</html>
