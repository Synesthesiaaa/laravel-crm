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
    @vite(['resources/css/app.css', 'resources/js/login-page.js'])
</head>
<body>
    <div class="login-root">
        <div class="login-bg" id="login-smokey-mount" aria-hidden="true">
            <canvas id="login-smokey-canvas"></canvas>
        </div>
        <div class="login-scrim" aria-hidden="true"></div>

        <button type="button" id="theme-toggle" class="theme-toggle login-theme-toggle" aria-label="Toggle light/dark mode" title="Toggle theme">
            <svg class="theme-icon-dark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
            <svg class="theme-icon-light hidden" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
        </button>

        <div class="login-content">
            <div class="login-glass-card">
                <h1>Sign in</h1>
                <p class="login-sub">Enter your credentials to continue</p>

                @if (session('status'))
                    <div class="login-alert login-alert--success" role="status">{{ session('status') }}</div>
                @endif
                @if ($errors->any())
                    <div class="login-alert login-alert--error" role="alert">{{ $errors->first() }}</div>
                @endif

                <form method="POST" action="{{ route('login') }}">
                    @csrf

                    <div class="login-field login-field-float">
                        <input id="username" type="text" name="username" value="{{ old('username') }}" required autofocus autocomplete="username" placeholder=" ">
                        <span class="login-field-icon" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        </span>
                        <label for="username">Username</label>
                    </div>

                    <div class="login-field login-field-float">
                        <input id="password" type="password" name="password" required autocomplete="current-password" placeholder=" ">
                        <span class="login-field-icon" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        </span>
                        <label for="password">Password</label>
                    </div>

                    @if (!empty($campaigns))
                    <div class="login-field login-field--select">
                        <label class="login-select-label" for="campaign">Campaign</label>
                        <div class="login-select-wrap">
                            <span class="login-field-icon login-field-icon--select" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 6h13"/><path d="M8 12h13"/><path d="M8 18h13"/><path d="M3 6h.01"/><path d="M3 12h.01"/><path d="M3 18h.01"/></svg>
                            </span>
                            <select id="campaign" name="campaign" class="login-select">
                                @foreach ($campaigns as $code => $config)
                                    <option value="{{ $code }}" {{ old('campaign', array_key_first($campaigns)) === $code ? 'selected' : '' }}>
                                        {{ $config['name'] ?? $code }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    @endif

                    <div class="login-field login-field--submit">
                        <button type="submit" class="login-btn">Sign in</button>
                    </div>
                </form>
            </div>
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
        if (btn) btn.addEventListener('click', function() {
          btn.classList.remove('login-theme-toggle--spin');
          void btn.offsetWidth;
          btn.classList.add('login-theme-toggle--spin');
          var next = html.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
          applyTheme(next);
          setTimeout(function() { btn.classList.remove('login-theme-toggle--spin'); }, 560);
        });
        applyTheme(html.getAttribute('data-theme') || 'dark');
      })();
    </script>
</body>
</html>
