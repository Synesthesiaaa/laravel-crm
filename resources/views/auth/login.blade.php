<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script>
      (function() { var t = localStorage.getItem('theme') || 'dark'; document.documentElement.setAttribute('data-theme', t); })();
    </script>
    <title>Login - {{ config('app.name') }}</title>
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
            max-width: 400px;
            width: 100%;
            animation: cardReveal 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .login-card h1 {
            margin: 0 0 0.25rem;
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--color-on-surface);
        }
        .login-card .sub {
            color: var(--color-on-surface-muted);
            font-size: 0.9375rem;
            margin-bottom: 1.75rem;
        }
        .form-group { margin-bottom: 1.25rem; }
        .form-group label {
            display: block;
            margin-bottom: 0.375rem;
            font-weight: 500;
            font-size: 0.875rem;
            color: var(--color-on-surface-muted);
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 0.75rem 1rem;
            background: var(--color-surface-2);
            border: 1px solid var(--color-border);
            border-radius: 8px;
            color: var(--color-on-surface);
            font-size: 0.9375rem;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px var(--color-primary-muted);
        }
        .form-group input::placeholder { color: var(--color-on-surface-dim); }
        .login-error {
            background: rgba(239, 68, 68, 0.12);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            font-size: 0.875rem;
            margin-bottom: 1.25rem;
            animation: slideDown 0.3s ease-out;
        }
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
            margin-top: 0.5rem;
            transition: background-color 0.2s ease, transform 0.15s ease, box-shadow 0.2s ease;
        }
        .btn-login:hover {
            background: var(--color-primary-hover);
            box-shadow: 0 0 20px rgba(233, 30, 140, 0.25);
        }
        .btn-login:active { transform: scale(0.98); }
    </style>
</head>
<body>
    <div class="login-page">
        <button type="button" id="theme-toggle" class="theme-toggle login-theme-toggle" aria-label="Toggle light/dark mode" title="Toggle theme">
            <svg class="theme-icon-dark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
            <svg class="theme-icon-light hidden" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
        </button>
        <div class="login-card">
            <h1>Sign in</h1>
            <p class="sub">Enter your credentials to continue</p>
            @if ($errors->any())
                <div class="login-error">{{ $errors->first() }}</div>
            @endif
            <form method="POST" action="{{ route('login') }}">
                @csrf
                <div class="form-group">
                    <label for="username">Username</label>
                    <input id="username" type="text" name="username" value="{{ old('username') }}" required autofocus autocomplete="username" placeholder="Your username">
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input id="password" type="password" name="password" required autocomplete="current-password" placeholder="••••••••">
                </div>
                @if (!empty($campaigns))
                <div class="form-group">
                    <label for="campaign">Campaign</label>
                    <select id="campaign" name="campaign">
                        @foreach ($campaigns as $code => $config)
                            <option value="{{ $code }}" {{ old('campaign', array_key_first($campaigns)) === $code ? 'selected' : '' }}>
                                {{ $config['name'] ?? $code }}
                            </option>
                        @endforeach
                    </select>
                </div>
                @endif
                <div class="form-group" style="margin-top: 1.5rem;">
                    <button type="submit" class="btn-login">Sign in</button>
                </div>
            </form>
        </div>
    </div>
    <script>
      (function() {
        var html = document.documentElement;
        var btn = document.getElementById('theme-toggle');
        var iconDark = btn && btn.querySelector('.theme-icon-dark');
        var iconLight = btn && btn.querySelector('.theme-icon-light');
        function applyTheme(theme) {
          html.setAttribute('data-theme', theme);
          try { localStorage.setItem('theme', theme); } catch (e) {}
          if (iconDark) iconDark.classList.toggle('hidden', theme === 'light');
          if (iconLight) iconLight.classList.toggle('hidden', theme !== 'light');
          if (btn) { btn.setAttribute('title', theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode'); btn.setAttribute('aria-label', theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode'); }
        }
        if (btn) btn.addEventListener('click', function() { var next = html.getAttribute('data-theme') === 'light' ? 'dark' : 'light'; applyTheme(next); });
        applyTheme(html.getAttribute('data-theme') || 'dark');
      })();
    </script>
</body>
</html>
