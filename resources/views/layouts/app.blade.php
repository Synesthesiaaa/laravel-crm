<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script>
      (function() {
        var t = localStorage.getItem('theme') || 'dark';
        document.documentElement.setAttribute('data-theme', t);
      })();
    </script>
    <title>@yield('title', config('app.name'))</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="min-h-screen flex" style="margin: 0;">
    @include('layouts.sidebar')
    <div id="main-layout" class="md-main-layout flex-1">
        <header class="md-header">
            <h1 class="text-lg font-bold tracking-tight flex items-center gap-3 text-[var(--color-on-surface)]">
                @yield('header-icon', '')
                @yield('header-title', 'Dashboard')
            </h1>
            <div class="flex items-center gap-4 shrink-0">
                @yield('header-actions')
                <button type="button" id="theme-toggle" class="theme-toggle inline-flex items-center justify-center shrink-0 w-9 h-9 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface-2)] text-[var(--color-on-surface-muted)] hover:bg-[var(--color-surface-3)] hover:text-[var(--color-primary)] transition-colors cursor-pointer" style="min-width: 2.25rem; min-height: 2.25rem;" aria-label="Toggle light/dark mode" title="Toggle theme">
                    <svg class="theme-icon-dark w-5 h-5" width="20" height="20" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                    <svg class="theme-icon-light w-5 h-5 hidden" width="20" height="20" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                </button>
                <span class="text-sm text-[var(--color-on-surface-muted)]">{{ $user->full_name ?? $user->name ?? $user->username }}</span>
                <a href="{{ route('logout') }}" class="text-sm text-[var(--color-on-surface-muted)] hover:text-[var(--color-primary)] transition-colors duration-200" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">Logout</a>
            </div>
        </header>
        <form id="logout-form" action="{{ route('logout') }}" method="POST" class="hidden">
            @csrf
        </form>
        <div class="content-padding p-8">
            @if (session('success'))
                <div class="alert-success mb-6">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="alert-error mb-6">{{ session('error') }}</div>
            @endif
            @yield('content')
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
          if (btn) btn.setAttribute('title', theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode');
          if (btn) btn.setAttribute('aria-label', theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode');
        }
        function toggleTheme() {
          var next = html.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
          applyTheme(next);
        }
        if (btn) btn.addEventListener('click', toggleTheme);
        var t = html.getAttribute('data-theme') || 'dark';
        applyTheme(t);
      })();
    </script>
    @stack('scripts')
</body>
</html>
