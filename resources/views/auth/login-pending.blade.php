<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script>
      (function() { var t = localStorage.getItem('theme') || 'dark'; document.documentElement.setAttribute('data-theme', t); })();
    </script>
    <title>Active session — {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .login-page { position: relative; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: var(--color-surface); padding: 1.5rem; }
        .login-card {
            background: var(--color-surface-card);
            border: 1px solid var(--color-border);
            border-radius: 16px;
            box-shadow: var(--shadow-elevation-2);
            padding: 2.5rem;
            max-width: 420px;
            width: 100%;
        }
        .login-card h1 { margin: 0 0 0.75rem; font-size: 1.5rem; font-weight: 700; color: var(--color-on-surface); }
        .login-card .sub { color: var(--color-on-surface-muted); font-size: 0.9375rem; margin-bottom: 1.5rem; line-height: 1.5; }
        .login-warn {
            background: rgba(234, 179, 8, 0.12);
            border: 1px solid rgba(234, 179, 8, 0.35);
            color: var(--color-on-surface);
            padding: 0.875rem 1rem;
            border-radius: 8px;
            font-size: 0.875rem;
            margin-bottom: 1.25rem;
        }
        .btn-row { display: flex; flex-direction: column; gap: 0.75rem; margin-top: 1rem; }
        .btn-login {
            width: 100%;
            padding: 0.875rem 1.25rem;
            background: var(--color-primary);
            color: var(--color-primary-foreground);
            font-weight: 600;
            font-size: 0.9375rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }
        .btn-login:hover { background: var(--color-primary-hover); }
        .btn-ghost {
            width: 100%;
            padding: 0.75rem 1.25rem;
            background: transparent;
            color: var(--color-on-surface-muted);
            font-weight: 500;
            font-size: 0.875rem;
            border: 1px solid var(--color-border);
            border-radius: 8px;
            cursor: pointer;
        }
        .btn-ghost:hover { background: var(--color-surface-2); color: var(--color-on-surface); }
    </style>
</head>
<body>
    <div class="login-page">
        <div class="login-card">
            <h1>Already signed in elsewhere</h1>
            <p class="sub">This account has an active session on another device or browser. Continue here to sign out those sessions and use the CRM on this device.</p>
            <div class="login-warn">
                If you did not expect this, choose <strong>Cancel</strong> and change your password before continuing.
            </div>
            @if ($errors->any())
                <div class="login-warn" style="border-color: rgba(239, 68, 68, 0.4); background: rgba(239, 68, 68, 0.1);">{{ $errors->first() }}</div>
            @endif
            <div class="btn-row">
                <form method="POST" action="{{ route('login.pending.confirm') }}">
                    @csrf
                    <button type="submit" class="btn-login">Continue and sign out other sessions</button>
                </form>
                <form method="POST" action="{{ route('login.pending.cancel') }}">
                    @csrf
                    <button type="submit" class="btn-ghost">Cancel</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
